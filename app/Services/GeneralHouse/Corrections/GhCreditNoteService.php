<?php

namespace App\Services\GeneralHouse\Corrections;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhCreditNoteEvent;
use App\Models\GeneralHouse\GhCreditNoteLine;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhInvoiceLine;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhCreditNoteReason;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GhCreditNoteService
{
    public function __construct(
        private readonly GhCreditNoteNumberService $numbers,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    /**
     * @param  list<array{invoice_line_id:int, quantity_credited:int}>  $lines
     */
    public function createDraft(GhInvoice $invoice, User $actor, array $input): GhCreditNote
    {
        $this->assertCreditNotesEnabled();
        $this->assertIssuedInvoice($invoice);

        $idempotency = (string) ($input['idempotency_key'] ?? Str::uuid());
        $existing = GhCreditNote::query()->where('idempotency_key', $idempotency)->first();
        if ($existing !== null) {
            return $existing;
        }

        $reasonCode = (string) ($input['reason_code'] ?? GhCreditNoteReason::OTHER);
        if (! in_array($reasonCode, GhCreditNoteReason::ALL, true)) {
            throw ValidationException::withMessages(['reason_code' => 'Invalid credit-note reason.']);
        }

        $reasonText = trim((string) ($input['reason_text'] ?? ''));
        if ($reasonText === '') {
            throw ValidationException::withMessages(['reason_text' => 'Reason text is required.']);
        }

        $built = $this->buildLines($invoice, $input['lines'] ?? []);

        return DB::transaction(function () use ($invoice, $actor, $idempotency, $reasonCode, $reasonText, $built) {
            $note = new GhCreditNote;
            $note->forceFill([
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'number' => null,
                'status' => GhCreditNoteStatus::DRAFT,
                'currency' => $invoice->currency ?: 'AED',
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'lines_net' => $built['lines_net'],
                'vat_amount' => $built['vat_amount'],
                'total_credit' => $built['total_credit'],
                'standard_vat_rate' => $invoice->standard_vat_rate,
                'idempotency_key' => $idempotency,
                'created_by' => $actor->id,
            ])->save();

            foreach ($built['lines'] as $i => $row) {
                $line = new GhCreditNoteLine;
                $line->forceFill(array_merge($row, [
                    'credit_note_id' => $note->id,
                    'sort_order' => $i,
                ]))->save();
            }

            $this->recordEvent($note, $actor, 'CREATED', null, GhCreditNoteStatus::DRAFT, [
                'total_credit' => $built['total_credit'],
            ]);

            return $note->fresh(['lines']);
        });
    }

    public function submitForApproval(GhCreditNote $note, User $actor): GhCreditNote
    {
        $this->assertCreditNotesEnabled();

        return DB::transaction(function () use ($note, $actor) {
            $note = GhCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status !== GhCreditNoteStatus::DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft credit notes can be submitted.']);
            }
            $this->assertEligibleTotals($note);

            $from = $note->status;
            $note->forceFill([
                'status' => GhCreditNoteStatus::PENDING_APPROVAL,
                'submitted_by' => $actor->id,
                'submitted_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($note, $actor, 'SUBMITTED', $from, GhCreditNoteStatus::PENDING_APPROVAL);

            return $note->fresh(['lines']);
        });
    }

    public function approve(GhCreditNote $note, User $actor): GhCreditNote
    {
        $this->assertCreditNotesEnabled();

        return DB::transaction(function () use ($note, $actor) {
            $note = GhCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status !== GhCreditNoteStatus::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Credit note is not pending approval.']);
            }
            if (GhLaunchConfig::requireDistinctApprover()
                && (int) $note->submitted_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'approver' => 'Approver must be different from the submitter (separation of duties).',
                ]);
            }
            $this->assertEligibleTotals($note);

            $from = $note->status;
            $note->forceFill([
                'status' => GhCreditNoteStatus::APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($note, $actor, 'APPROVED', $from, GhCreditNoteStatus::APPROVED);

            return $note->fresh(['lines']);
        });
    }

    public function reject(GhCreditNote $note, User $actor, string $reason): GhCreditNote
    {
        $this->assertCreditNotesEnabled();
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Rejection reason is required.']);
        }

        return DB::transaction(function () use ($note, $actor, $reason) {
            $note = GhCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status !== GhCreditNoteStatus::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Only pending credit notes can be rejected.']);
            }
            $from = $note->status;
            $note->forceFill([
                'status' => GhCreditNoteStatus::REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => Carbon::now('UTC'),
                'rejection_reason' => $reason,
            ])->save();
            $this->recordEvent($note, $actor, 'REJECTED', $from, GhCreditNoteStatus::REJECTED, ['reason' => $reason]);

            return $note->fresh(['lines']);
        });
    }

    public function issue(GhCreditNote $note, User $actor): GhCreditNote
    {
        $this->assertCreditNotesEnabled();

        return DB::transaction(function () use ($note, $actor) {
            $note = GhCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->status === GhCreditNoteStatus::ISSUED) {
                return $note->fresh(['lines']);
            }
            if ($note->status !== GhCreditNoteStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Only approved credit notes can be issued.']);
            }

            $invoice = GhInvoice::query()->whereKey($note->invoice_id)->lockForUpdate()->firstOrFail();
            $this->assertEligibleTotals($note, $invoice);

            $from = $note->status;
            $number = $note->number ?: $this->numbers->nextNumber();
            $note->forceFill([
                'number' => $number,
                'status' => GhCreditNoteStatus::ISSUED,
                'issued_by' => $actor->id,
                'issued_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($note, $actor, 'ISSUED', $from, GhCreditNoteStatus::ISSUED, ['number' => $number]);
            $this->balances->syncInvoicePaymentStatus($invoice);

            return $note->fresh(['lines']);
        });
    }

    public function cancel(GhCreditNote $note, User $actor): GhCreditNote
    {
        $this->assertCreditNotesEnabled();

        return DB::transaction(function () use ($note, $actor) {
            $note = GhCreditNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            if (! in_array($note->status, [GhCreditNoteStatus::DRAFT, GhCreditNoteStatus::PENDING_APPROVAL, GhCreditNoteStatus::APPROVED], true)) {
                throw ValidationException::withMessages(['status' => 'Issued credit notes cannot be cancelled.']);
            }
            $from = $note->status;
            $note->forceFill([
                'status' => GhCreditNoteStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($note, $actor, 'CANCELLED', $from, GhCreditNoteStatus::CANCELLED);

            return $note->fresh(['lines']);
        });
    }

    /**
     * @param  list<array{invoice_line_id:int, quantity_credited:int}>  $rawLines
     * @return array{lines: list<array<string, mixed>>, lines_net: string, vat_amount: string, total_credit: string}
     */
    private function buildLines(GhInvoice $invoice, array $rawLines): array
    {
        if ($rawLines === []) {
            throw ValidationException::withMessages(['lines' => 'At least one credit line is required.']);
        }

        $invoice->loadMissing('lines');
        $linesById = $invoice->lines->keyBy('id');
        $creditedQty = $this->issuedCreditedQuantities($invoice->id);

        $out = [];
        $netSum = '0';
        $vatSum = '0';
        $totalSum = '0';

        foreach ($rawLines as $idx => $raw) {
            $lineId = (int) ($raw['invoice_line_id'] ?? 0);
            $qty = (int) ($raw['quantity_credited'] ?? 0);
            /** @var GhInvoiceLine|null $invLine */
            $invLine = $linesById->get($lineId);
            if ($invLine === null) {
                throw ValidationException::withMessages(["lines.{$idx}.invoice_line_id" => 'Invoice line not found on this invoice.']);
            }
            if ($qty < 1) {
                throw ValidationException::withMessages(["lines.{$idx}.quantity_credited" => 'Quantity credited must be at least 1.']);
            }

            $already = (int) ($creditedQty[$lineId] ?? 0);
            $remainingQty = (int) $invLine->quantity - $already;
            if ($qty > $remainingQty) {
                throw ValidationException::withMessages([
                    "lines.{$idx}.quantity_credited" => "Quantity exceeds remaining eligible quantity ({$remainingQty}).",
                ]);
            }

            $origQty = max(1, (int) $invLine->quantity);
            $share = GhMoney::div((string) $qty, (string) $origQty, 8);
            $lineNet = GhMoney::of(GhMoney::mul((string) $invLine->taxable_base, $share));
            $lineVat = GhMoney::of(GhMoney::mul((string) $invLine->tax_amount, $share));
            $lineTotal = GhMoney::of(GhMoney::add($lineNet, $lineVat));

            $out[] = [
                'invoice_line_id' => $invLine->id,
                'quantity_credited' => $qty,
                'unit_price' => GhMoney::of((string) $invLine->unit_price),
                'currency' => $invLine->currency ?: 'AED',
                'tax_class' => $invLine->tax_class,
                'tax_rate' => GhMoney::of((string) $invLine->tax_rate),
                'line_net' => $lineNet,
                'line_vat' => $lineVat,
                'line_total' => $lineTotal,
                'product_name_ar' => $invLine->product_name_ar,
                'product_name_en' => $invLine->product_name_en,
                'treatment_name_ar' => $invLine->treatment_name_ar,
                'treatment_name_en' => $invLine->treatment_name_en,
                'snapshot' => [
                    'invoice_line_id' => $invLine->id,
                    'quantity' => $invLine->quantity,
                    'taxable_base' => (string) $invLine->taxable_base,
                    'tax_amount' => (string) $invLine->tax_amount,
                    'line_total' => (string) $invLine->line_total,
                    'tax_class' => $invLine->tax_class,
                ],
            ];

            $netSum = GhMoney::add($netSum, $lineNet);
            $vatSum = GhMoney::add($vatSum, $lineVat);
            $totalSum = GhMoney::add($totalSum, $lineTotal);
            $creditedQty[$lineId] = $already + $qty;
        }

        $totalCredit = GhMoney::of($totalSum);
        $issuedCredits = $this->balances->sumIssuedCredits($invoice->id);
        $cap = GhMoney::of(GhMoney::sub((string) $invoice->total_payable, $issuedCredits));
        if (GhMoney::cmp($totalCredit, $cap) > 0) {
            throw ValidationException::withMessages([
                'lines' => 'Cumulative credit would exceed the original invoice total.',
            ]);
        }
        if (GhMoney::cmp($totalCredit, '0') <= 0) {
            throw ValidationException::withMessages(['lines' => 'Credit total must be greater than zero.']);
        }

        return [
            'lines' => $out,
            'lines_net' => GhMoney::of($netSum),
            'vat_amount' => GhMoney::of($vatSum),
            'total_credit' => $totalCredit,
        ];
    }

    /** @return array<int, int> */
    private function issuedCreditedQuantities(int $invoiceId): array
    {
        $rows = GhCreditNoteLine::query()
            ->whereHas('creditNote', function ($q) use ($invoiceId) {
                $q->where('invoice_id', $invoiceId)
                    ->where('status', GhCreditNoteStatus::ISSUED);
            })
            ->selectRaw('invoice_line_id, SUM(quantity_credited) as qty')
            ->groupBy('invoice_line_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->invoice_line_id] = (int) $row->qty;
        }

        return $map;
    }

    private function assertEligibleTotals(GhCreditNote $note, ?GhInvoice $invoice = null): void
    {
        $invoice ??= GhInvoice::query()->findOrFail($note->invoice_id);
        $issued = $this->balances->sumIssuedCredits($invoice->id);
        // Exclude this note if already issued (re-check path)
        if ($note->status === GhCreditNoteStatus::ISSUED) {
            $issued = GhMoney::of(GhMoney::sub($issued, (string) $note->total_credit));
        }
        $cap = GhMoney::of(GhMoney::sub((string) $invoice->total_payable, $issued));
        if (GhMoney::cmp((string) $note->total_credit, $cap) > 0) {
            throw ValidationException::withMessages([
                'total_credit' => 'Credit exceeds remaining eligible invoice value.',
            ]);
        }
    }

    private function assertIssuedInvoice(GhInvoice $invoice): void
    {
        if ($invoice->status !== GhInvoiceStatus::ISSUED) {
            throw ValidationException::withMessages(['invoice' => 'Credit notes require an issued invoice.']);
        }
    }

    private function assertCreditNotesEnabled(): void
    {
        if (! GhLaunchConfig::creditNotesEnabled()) {
            throw ValidationException::withMessages(['credit_notes' => 'Credit notes are disabled.']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        GhCreditNote $note,
        User $actor,
        string $type,
        ?string $from,
        string $to,
        array $payload = [],
    ): void {
        $event = new GhCreditNoteEvent;
        $event->forceFill([
            'credit_note_id' => $note->id,
            'invoice_id' => $note->invoice_id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'occurred_at' => Carbon::now('UTC'),
            'payload' => $payload === [] ? null : $payload,
        ])->save();
    }
}
