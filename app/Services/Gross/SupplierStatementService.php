<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossPurchaseReturn;
use App\Models\Gross\GrossSupplier;
use App\Models\Gross\GrossTreasuryVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Supplier account statement: confirmed purchases (+), returns (−), treasury payments (−).
 * No aging / due dates.
 */
class SupplierStatementService
{
    private const MAX_ENTRIES = 500;

    /**
     * Owed balances for a page of suppliers (one currency), SQL aggregates — no N+1.
     *
     * @param  list<int>|Collection<int, int>  $supplierIds
     * @return array<int, string> supplier_id => money string (purchases − returns − payments)
     */
    public function balancesForSuppliers(iterable $supplierIds, string $currency = 'EGP'): array
    {
        $ids = collect($supplierIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $currency = strtoupper(trim($currency));
        if (! in_array($currency, ['EGP', 'AED'], true)) {
            $currency = 'EGP';
        }

        $purchases = GrossPurchaseInvoice::query()
            ->whereIn('supplier_id', $ids)
            ->where('status', GrossPurchaseInvoice::STATUS_CONFIRMED)
            ->where('currency', $currency)
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, COALESCE(SUM(total), 0) as amount')
            ->pluck('amount', 'supplier_id');

        $returnTotals = GrossPurchaseReturn::query()
            ->join('gross_purchase_invoices', 'gross_purchase_invoices.id', '=', 'gross_purchase_returns.purchase_invoice_id')
            ->join('gross_purchase_return_lines', 'gross_purchase_return_lines.purchase_return_id', '=', 'gross_purchase_returns.id')
            ->whereIn('gross_purchase_invoices.supplier_id', $ids)
            ->where('gross_purchase_invoices.currency', $currency)
            ->where('gross_purchase_invoices.status', GrossPurchaseInvoice::STATUS_CONFIRMED)
            ->groupBy('gross_purchase_invoices.supplier_id')
            ->selectRaw('gross_purchase_invoices.supplier_id as supplier_id, COALESCE(SUM(gross_purchase_return_lines.line_total), 0) as amount')
            ->pluck('amount', 'supplier_id');

        $payments = GrossTreasuryVoucher::query()
            ->whereIn('supplier_id', $ids)
            ->where('type', GrossTreasuryVoucher::TYPE_PAYMENT)
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_SUPPLIER)
            ->where('currency', $currency)
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, COALESCE(SUM(amount), 0) as amount')
            ->pluck('amount', 'supplier_id');

        $out = [];
        foreach ($ids as $id) {
            $balance = round(
                (float) ($purchases[$id] ?? 0)
                - (float) ($returnTotals[$id] ?? 0)
                - (float) ($payments[$id] ?? 0),
                2
            );
            $out[$id] = number_format($balance, 2, '.', '');
        }

        return $out;
    }

    /**
     * @param  array{currency?: string, from?: string, to?: string}  $filters
     * @return array{
     *     currency: string,
     *     from: string|null,
     *     to: string|null,
     *     entries: list<array{
     *         at: Carbon,
     *         type: string,
     *         reference: string,
     *         debit: string,
     *         credit: string,
     *         balance: string,
     *         url: string|null
     *     }>,
     *     total_purchases: string,
     *     total_returns: string,
     *     total_payments: string,
     *     balance: string,
     *     truncated: bool
     * }
     */
    public function build(GrossSupplier $supplier, array $filters = []): array
    {
        $currency = strtoupper(trim((string) ($filters['currency'] ?? 'EGP')));
        if (! in_array($currency, ['EGP', 'AED'], true)) {
            $currency = 'EGP';
        }

        $from = $this->parseDate($filters['from'] ?? null);
        $to = $this->parseDate($filters['to'] ?? null);

        $rows = $this->purchaseRows($supplier, $currency, $from, $to)
            ->concat($this->returnRows($supplier, $currency, $from, $to))
            ->concat($this->paymentRows($supplier, $currency, $from, $to))
            ->sortBy(fn (array $row) => $row['at']->timestamp.'-'.$row['type'].'-'.$row['id'])
            ->values();

        $truncated = $rows->count() > self::MAX_ENTRIES;
        if ($truncated) {
            $rows = $rows->take(self::MAX_ENTRIES)->values();
        }

        $running = 0.0;
        $totalPurchases = 0.0;
        $totalReturns = 0.0;
        $totalPayments = 0.0;
        $entries = [];

        foreach ($rows as $row) {
            $amount = (float) $row['amount'];
            if ($row['type'] === 'PURCHASE') {
                $running = round($running + $amount, 2);
                $totalPurchases = round($totalPurchases + $amount, 2);
                $debit = $this->money($amount);
                $credit = $this->money(0);
            } else {
                $running = round($running - $amount, 2);
                if ($row['type'] === 'RETURN') {
                    $totalReturns = round($totalReturns + $amount, 2);
                } else {
                    $totalPayments = round($totalPayments + $amount, 2);
                }
                $debit = $this->money(0);
                $credit = $this->money($amount);
            }

            $entries[] = [
                'at' => $row['at'],
                'type' => $row['type'],
                'reference' => $row['reference'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $this->money($running),
                'url' => $row['url'],
            ];
        }

        return [
            'currency' => $currency,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'entries' => $entries,
            'total_purchases' => $this->money($totalPurchases),
            'total_returns' => $this->money($totalReturns),
            'total_payments' => $this->money($totalPayments),
            'balance' => $this->money($running),
            'truncated' => $truncated,
        ];
    }

    /**
     * @return Collection<int, array{id: int, at: Carbon, type: string, reference: string, amount: float, url: string|null}>
     */
    private function purchaseRows(
        GrossSupplier $supplier,
        string $currency,
        ?Carbon $from,
        ?Carbon $to,
    ): Collection {
        $q = GrossPurchaseInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', GrossPurchaseInvoice::STATUS_CONFIRMED)
            ->where('currency', $currency)
            ->orderBy('confirmed_at')
            ->orderBy('id');

        if ($from !== null) {
            $q->whereDate('confirmed_at', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $q->whereDate('confirmed_at', '<=', $to->toDateString());
        }

        return $q->get()->map(fn (GrossPurchaseInvoice $inv) => [
            'id' => $inv->id,
            'at' => $inv->confirmed_at ?? $inv->created_at ?? now(),
            'type' => 'PURCHASE',
            'reference' => $inv->invoice_number,
            'amount' => (float) $inv->total,
            'url' => route('gross.purchases.show', $inv),
        ]);
    }

    /**
     * @return Collection<int, array{id: int, at: Carbon, type: string, reference: string, amount: float, url: string|null}>
     */
    private function returnRows(
        GrossSupplier $supplier,
        string $currency,
        ?Carbon $from,
        ?Carbon $to,
    ): Collection {
        $q = GrossPurchaseReturn::query()
            ->with(['lines', 'invoice'])
            ->whereHas('invoice', function ($inv) use ($supplier, $currency): void {
                $inv->where('supplier_id', $supplier->id)
                    ->where('currency', $currency);
            })
            ->orderBy('created_at')
            ->orderBy('id');

        if ($from !== null) {
            $q->whereDate('created_at', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $q->whereDate('created_at', '<=', $to->toDateString());
        }

        return $q->get()->map(function (GrossPurchaseReturn $ret) {
            $amount = round((float) $ret->lines->sum(fn ($l) => (float) $l->line_total), 2);

            return [
                'id' => $ret->id,
                'at' => $ret->created_at ?? now(),
                'type' => 'RETURN',
                'reference' => $ret->return_number,
                'amount' => $amount,
                'url' => $ret->invoice
                    ? route('gross.purchases.show', $ret->invoice)
                    : null,
            ];
        });
    }

    /**
     * @return Collection<int, array{id: int, at: Carbon, type: string, reference: string, amount: float, url: string|null}>
     */
    private function paymentRows(
        GrossSupplier $supplier,
        string $currency,
        ?Carbon $from,
        ?Carbon $to,
    ): Collection {
        $q = GrossTreasuryVoucher::query()
            ->where('supplier_id', $supplier->id)
            ->where('type', GrossTreasuryVoucher::TYPE_PAYMENT)
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_SUPPLIER)
            ->where('currency', $currency)
            ->orderBy('voucher_date')
            ->orderBy('id');

        if ($from !== null) {
            $q->whereDate('voucher_date', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $q->whereDate('voucher_date', '<=', $to->toDateString());
        }

        return $q->get()->map(function (GrossTreasuryVoucher $v) {
            return [
                'id' => $v->id,
                'at' => Carbon::parse($v->voucher_date)->startOfDay(),
                'type' => 'PAYMENT',
                'reference' => $v->voucher_number,
                'amount' => (float) $v->amount,
                'url' => route('gross.treasury.show', $v),
            ];
        });
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
