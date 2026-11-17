<?php

namespace App\Services\GeneralHouse\Corrections;

use App\Models\GeneralHouse\GhDebitAdjustment;
use App\Models\GeneralHouse\GhDebitAdjustmentEvent;
use App\Models\GeneralHouse\GhDebitAdjustmentLine;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhInvoiceLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhDebitAdjustmentLineType;
use App\Support\GeneralHouse\GhDebitAdjustmentReason;
use App\Support\GeneralHouse\GhDebitAdjustmentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Positive debit adjustments against an issued invoice (Option A).
 * Never mutates issued invoice money columns.
 */
final class GhDebitAdjustmentService
{
    public function __construct(
        private readonly GhDebitAdjustmentNumberService $numbers,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    /**
     * Preview totals without persisting.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{lines_net: string, vat_amount: string, total_debit: string, lines: list<array<string, mixed>>}
     */
    public function preview(GhInvoice $invoice, array $lines): array
    {
        $this->assertDebitsEnabled();
        $this->assertIssuedInvoice($invoice);

        return $this->buildLines($invoice, $lines);
    }

    /**
     * @param  array{
     *   reason_code?: string,
     *   reason_text?: string,
     *   idempotency_key?: string,
     *   lines: list<array<string, mixed>>,
     * }  $input
     */
    public function createDraft(GhInvoice $invoice, User $actor, array $input): GhDebitAdjustment
    {
        $this->assertDebitsEnabled();
        $this->assertIssuedInvoice($invoice);

        $idempotency = (string) ($input['idempotency_key'] ?? Str::uuid());
        $existing = GhDebitAdjustment::query()->where('idempotency_key', $idempotency)->first();
        if ($existing !== null) {
            return $existing;
        }

        $reasonCode = (string) ($input['reason_code'] ?? GhDebitAdjustmentReason::OTHER);
        if (! in_array($reasonCode, GhDebitAdjustmentReason::ALL, true)) {
            throw ValidationException::withMessages([
                'reason_code' => [__('gh.errors.debit_adjustment_reason_invalid')],
            ]);
        }

        $reasonText = trim((string) ($input['reason_text'] ?? ''));
        if ($reasonText === '') {
            throw ValidationException::withMessages([
                'reason_text' => [__('gh.errors.debit_adjustment_reason_required')],
            ]);
        }

        $built = $this->buildLines($invoice, $input['lines'] ?? []);

        return DB::transaction(function () use ($invoice, $actor, $idempotency, $reasonCode, $reasonText, $built) {
            $adj = new GhDebitAdjustment;
            $adj->forceFill([
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'number' => null,
                'status' => GhDebitAdjustmentStatus::DRAFT,
                'currency' => $invoice->currency ?: 'AED',
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'lines_net' => $built['lines_net'],
                'vat_amount' => $built['vat_amount'],
                'total_debit' => $built['total_debit'],
                'standard_vat_rate' => $invoice->standard_vat_rate,
                'idempotency_key' => $idempotency,
                'created_by' => $actor->id,
            ])->save();

            foreach ($built['lines'] as $i => $row) {
                $line = new GhDebitAdjustmentLine;
                $line->forceFill(array_merge($row, [
                    'debit_adjustment_id' => $adj->id,
                    'sort_order' => $i,
                ]))->save();
            }

            $balance = $this->balances->forInvoice($invoice);
            $this->recordEvent($adj, $actor, 'CREATED', null, GhDebitAdjustmentStatus::DRAFT, [
                'total_debit' => $built['total_debit'],
                'vat_amount' => $built['vat_amount'],
                'outstanding_before' => $balance['remaining_due'],
            ]);

            return $adj->fresh(['lines']);
        });
    }

    public function issue(GhDebitAdjustment $adjustment, User $actor): GhDebitAdjustment
    {
        $this->assertDebitsEnabled();

        return DB::transaction(function () use ($adjustment, $actor) {
            $adj = GhDebitAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($adj->status === GhDebitAdjustmentStatus::ISSUED) {
                return $adj->fresh(['lines']);
            }
            if ($adj->status !== GhDebitAdjustmentStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => [__('gh.errors.debit_adjustment_not_draft')],
                ]);
            }

            $invoice = GhInvoice::query()->whereKey($adj->invoice_id)->lockForUpdate()->firstOrFail();
            $this->assertIssuedInvoice($invoice);
            if (GhMoney::cmp((string) $adj->total_debit, '0') <= 0) {
                throw ValidationException::withMessages([
                    'total_debit' => [__('gh.errors.debit_adjustment_total_invalid')],
                ]);
            }

            $before = $this->balances->forInvoice($invoice);
            $from = $adj->status;
            $number = $adj->number ?: $this->numbers->nextNumber();
            $adj->forceFill([
                'number' => $number,
                'status' => GhDebitAdjustmentStatus::ISSUED,
                'issued_by' => $actor->id,
                'issued_at' => Carbon::now('UTC'),
            ])->save();

            $this->balances->syncInvoicePaymentStatus($invoice);
            $after = $this->balances->forInvoice($invoice->fresh());

            $this->recordEvent($adj, $actor, 'ISSUED', $from, GhDebitAdjustmentStatus::ISSUED, [
                'number' => $number,
                'total_debit' => (string) $adj->total_debit,
                'vat_amount' => (string) $adj->vat_amount,
                'outstanding_before' => $before['remaining_due'],
                'outstanding_after' => $after['remaining_due'],
                'invoice_total_unchanged' => (string) $invoice->total_payable,
            ]);

            return $adj->fresh(['lines']);
        });
    }

    /**
     * Create draft and issue in one authorized manager action (preview already done client-side).
     *
     * @param  array<string, mixed>  $input
     */
    public function createAndIssue(GhInvoice $invoice, User $actor, array $input): GhDebitAdjustment
    {
        return DB::transaction(function () use ($invoice, $actor, $input) {
            $draft = $this->createDraft($invoice, $actor, $input);

            return $this->issue($draft, $actor);
        });
    }

    public function cancel(GhDebitAdjustment $adjustment, User $actor): GhDebitAdjustment
    {
        $this->assertDebitsEnabled();

        return DB::transaction(function () use ($adjustment, $actor) {
            $adj = GhDebitAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($adj->status === GhDebitAdjustmentStatus::CANCELLED) {
                return $adj->fresh(['lines']);
            }
            if ($adj->status !== GhDebitAdjustmentStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => [__('gh.errors.debit_adjustment_cancel_not_draft')],
                ]);
            }

            $from = $adj->status;
            $adj->forceFill([
                'status' => GhDebitAdjustmentStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($adj, $actor, 'CANCELLED', $from, GhDebitAdjustmentStatus::CANCELLED);

            return $adj->fresh(['lines']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rawLines
     * @return array{lines_net: string, vat_amount: string, total_debit: string, lines: list<array<string, mixed>>}
     */
    private function buildLines(GhInvoice $invoice, array $rawLines): array
    {
        if ($rawLines === []) {
            throw ValidationException::withMessages([
                'lines' => [__('gh.errors.debit_adjustment_lines_required')],
            ]);
        }

        $vatRate = GhMoney::of((string) ($invoice->standard_vat_rate ?? config('general_house.standard_vat_rate', '5.00')));
        $built = [];
        $netSum = '0.00';
        $vatSum = '0.00';

        foreach (array_values($rawLines) as $i => $raw) {
            $type = strtoupper(trim((string) ($raw['line_type'] ?? '')));
            if (! in_array($type, GhDebitAdjustmentLineType::ALL, true)) {
                throw ValidationException::withMessages([
                    "lines.$i.line_type" => [__('gh.errors.debit_adjustment_line_type_invalid')],
                ]);
            }

            $qty = (int) ($raw['quantity'] ?? 0);
            if ($qty < 1) {
                throw ValidationException::withMessages([
                    "lines.$i.quantity" => [__('gh.errors.debit_adjustment_qty_invalid')],
                ]);
            }

            $unitPrice = GhMoney::of($raw['unit_price'] ?? '0');
            if (GhMoney::cmp($unitPrice, '0') <= 0) {
                throw ValidationException::withMessages([
                    "lines.$i.unit_price" => [__('gh.errors.debit_adjustment_price_invalid')],
                ]);
            }

            $invoiceLineId = null;
            $offeringId = null;
            $taxClass = GhTaxClass::STANDARD_RATED;
            $productAr = null;
            $productEn = null;
            $treatmentAr = null;
            $treatmentEn = null;
            $snapshot = ['line_type' => $type];

            if ($type === GhDebitAdjustmentLineType::ADDITIONAL_ITEM) {
                $offeringId = (int) ($raw['offering_id'] ?? 0);
                $offering = GhProductOffering::query()
                    ->with(['product', 'treatment'])
                    ->whereKey($offeringId)
                    ->first();
                if ($offering === null || $offering->status !== GhCatalogStatus::ACTIVE) {
                    throw ValidationException::withMessages([
                        "lines.$i.offering_id" => [__('gh.errors.debit_adjustment_offering_invalid')],
                    ]);
                }
                $taxClass = (string) ($offering->tax_class ?: GhTaxClass::STANDARD_RATED);
                $productAr = $offering->product?->name_ar;
                $productEn = $offering->product?->name_en;
                $treatmentAr = $offering->treatment?->name_ar;
                $treatmentEn = $offering->treatment?->name_en;
                $snapshot['offering_id'] = $offering->id;
                $snapshot['standard_price'] = (string) $offering->standard_price;
            } else {
                $invoiceLineId = (int) ($raw['invoice_line_id'] ?? 0);
                /** @var GhInvoiceLine|null $invLine */
                $invLine = GhInvoiceLine::query()
                    ->where('invoice_id', $invoice->id)
                    ->whereKey($invoiceLineId)
                    ->first();
                if ($invLine === null) {
                    throw ValidationException::withMessages([
                        "lines.$i.invoice_line_id" => [__('gh.errors.debit_adjustment_invoice_line_invalid')],
                    ]);
                }
                $taxClass = (string) ($invLine->tax_class ?: GhTaxClass::STANDARD_RATED);
                $productAr = $invLine->product_name_ar;
                $productEn = $invLine->product_name_en;
                $treatmentAr = $invLine->treatment_name_ar;
                $treatmentEn = $invLine->treatment_name_en;
                $offeringId = $invLine->offering_id;
                $snapshot['invoice_line_id'] = $invLine->id;
                $snapshot['original_unit_price'] = (string) $invLine->unit_price;
                $snapshot['original_quantity'] = (int) $invLine->quantity;
            }

            if (! in_array($taxClass, GhTaxClass::ALL, true)) {
                $taxClass = GhTaxClass::STANDARD_RATED;
            }

            $lineNet = GhMoney::roundHalfUp(GhMoney::mul((string) $qty, $unitPrice));
            $lineRate = GhTaxClass::isVatApplicable($taxClass) ? $vatRate : '0.00';
            $lineVat = GhTaxClass::isVatApplicable($taxClass)
                ? GhMoney::roundHalfUp(GhMoney::div(GhMoney::mul($lineNet, $lineRate), '100'))
                : '0.00';
            $lineTotal = GhMoney::roundHalfUp(GhMoney::add($lineNet, $lineVat));

            $built[] = [
                'line_type' => $type,
                'invoice_line_id' => $invoiceLineId,
                'offering_id' => $offeringId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'currency' => $invoice->currency ?: 'AED',
                'tax_class' => $taxClass,
                'tax_rate' => $lineRate,
                'line_net' => $lineNet,
                'line_vat' => $lineVat,
                'line_total' => $lineTotal,
                'product_name_ar' => $productAr,
                'product_name_en' => $productEn,
                'treatment_name_ar' => $treatmentAr,
                'treatment_name_en' => $treatmentEn,
                'snapshot' => $snapshot,
            ];

            $netSum = GhMoney::roundHalfUp(GhMoney::add($netSum, $lineNet));
            $vatSum = GhMoney::roundHalfUp(GhMoney::add($vatSum, $lineVat));
        }

        $total = GhMoney::roundHalfUp(GhMoney::add($netSum, $vatSum));
        if (GhMoney::cmp($total, '0') <= 0) {
            throw ValidationException::withMessages([
                'lines' => [__('gh.errors.debit_adjustment_total_invalid')],
            ]);
        }

        return [
            'lines_net' => $netSum,
            'vat_amount' => $vatSum,
            'total_debit' => $total,
            'lines' => $built,
        ];
    }

    private function assertDebitsEnabled(): void
    {
        if (! GhLaunchConfig::debitAdjustmentsEnabled()) {
            throw ValidationException::withMessages([
                'debit_adjustment' => [__('gh.errors.debit_adjustment_disabled')],
            ]);
        }
    }

    private function assertIssuedInvoice(GhInvoice $invoice): void
    {
        if ($invoice->status !== GhInvoiceStatus::ISSUED) {
            throw ValidationException::withMessages([
                'invoice' => [__('gh.errors.debit_adjustment_requires_issued')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        GhDebitAdjustment $adj,
        ?User $actor,
        string $type,
        ?string $from,
        ?string $to,
        array $payload = [],
    ): void {
        $event = new GhDebitAdjustmentEvent;
        $event->forceFill([
            'debit_adjustment_id' => $adj->id,
            'invoice_id' => $adj->invoice_id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'occurred_at' => Carbon::now('UTC'),
            'payload' => $payload,
        ])->save();
    }
}
