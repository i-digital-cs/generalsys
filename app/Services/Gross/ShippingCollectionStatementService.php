<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossTreasuryVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shipping company collection statement: merchandise COD owed by courier (+/−), collections (−).
 * Shipping/delivery fees stay with the shipping company — never in running balance.
 */
class ShippingCollectionStatementService
{
    private const MAX_ENTRIES = 500;

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
     *     total_invoices: string,
     *     total_adjustments: string,
     *     total_collections: string,
     *     total_delivery_fees: string,
     *     balance: string,
     *     truncated: bool
     * }
     */
    public function build(GrossShippingCompany $company, array $filters = []): array
    {
        $currency = strtoupper(trim((string) ($filters['currency'] ?? 'EGP')));
        if (! in_array($currency, ['EGP', 'AED'], true)) {
            $currency = 'EGP';
        }

        $from = $this->parseDate($filters['from'] ?? null);
        $to = $this->parseDate($filters['to'] ?? null);

        $invoices = GrossSalesInvoice::query()
            ->where('shipping_company_id', $company->id)
            ->where('currency', $currency)
            ->whereIn('status', [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ])
            ->with(['lines.allocation'])
            ->get();

        $returnsByInvoice = $this->returnMovementRows($company, $currency, null, null)
            ->groupBy(fn (array $row) => (int) ($row['invoice_id'] ?? 0));
        $treasuryByInvoice = $this->courierCodTreasuryByInvoice($invoices);

        $rows = $this->timelineRows($invoices, $returnsByInvoice, $treasuryByInvoice, $from, $to);

        $truncated = $rows->count() > self::MAX_ENTRIES;
        if ($truncated) {
            $rows = $rows->take(self::MAX_ENTRIES)->values();
        }

        $running = 0.0;
        $totalInvoices = 0.0;
        $totalAdjustments = 0.0;
        $totalCollections = 0.0;
        $totalDeliveryFees = 0.0;
        $entries = [];

        foreach ($rows as $row) {
            $amount = (float) $row['amount'];
            $affectsBalance = (bool) ($row['affects_balance'] ?? true);

            if ($row['type'] === 'DELIVERY_FEE') {
                $totalDeliveryFees = round($totalDeliveryFees + $amount, 2);
            }

            if ($affectsBalance) {
                if ($row['direction'] === 'debit') {
                    $running = round($running + $amount, 2);
                    $totalInvoices = round($totalInvoices + $amount, 2);
                    $debit = $this->money($amount);
                    $credit = $this->money(0);
                } else {
                    $running = round($running - $amount, 2);
                    if ($row['type'] === 'COLLECTION' || $row['type'] === 'TREASURY_RECEIPT') {
                        $totalCollections = round($totalCollections + $amount, 2);
                    } else {
                        $totalAdjustments = round($totalAdjustments + $amount, 2);
                    }
                    $debit = $this->money(0);
                    $credit = $this->money($amount);
                }
            } else {
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
                'informational' => ! $affectsBalance,
                'url' => $row['url'],
            ];
        }

        return [
            'currency' => $currency,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'entries' => $entries,
            'total_invoices' => $this->money($totalInvoices),
            'total_adjustments' => $this->money($totalAdjustments),
            'total_collections' => $this->money($totalCollections),
            'total_delivery_fees' => $this->money($totalDeliveryFees),
            'balance' => $this->money($running),
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  Collection<int, GrossSalesInvoice>  $invoices
     * @param  Collection<int|string, Collection<int, array<string, mixed>>>  $returnsByInvoice
     * @param  Collection<int, GrossTreasuryVoucher>  $treasuryByInvoice
     * @return Collection<int, array<string, mixed>>
     */
    private function timelineRows(
        Collection $invoices,
        Collection $returnsByInvoice,
        Collection $treasuryByInvoice,
        ?Carbon $from,
        ?Carbon $to,
    ): Collection {
        $rows = collect();

        $ordered = $invoices->sortBy(fn (GrossSalesInvoice $invoice) => sprintf(
            '%010d-%06d',
            ($invoice->confirmed_at ?? $invoice->created_at ?? now())->timestamp,
            $invoice->id,
        ));

        foreach ($ordered as $invoice) {
            $confirmedAt = $invoice->confirmed_at ?? $invoice->created_at ?? now();
            if ($this->inDateRange($confirmedAt, $from, $to)) {
                $rows->push([
                    'id' => $invoice->id,
                    'at' => $confirmedAt,
                    'type' => 'SALE',
                    'reference' => $invoice->invoice_number,
                    'amount' => $this->saleDebitAmount($invoice),
                    'direction' => 'debit',
                    'url' => route('gross.sales.show', $invoice),
                ]);
            }

            foreach ($returnsByInvoice->get($invoice->id, collect()) as $returnRow) {
                if ($this->returnRowsAreNettedInSale($invoice)) {
                    continue;
                }
                if ($this->inDateRange($returnRow['at'], $from, $to)) {
                    $rows->push($returnRow);
                }
            }

            if ($invoice->status === GrossSalesInvoice::STATUS_REFUSED && $invoice->refused_at !== null) {
                $collectionCredits = $this->collectedMerchandiseAmount($invoice);
                $refuseAmount = round(max(
                    0,
                    $this->originalMerchandiseAmount($invoice) - $collectionCredits,
                ), 2);
                if ($refuseAmount > 0 && $this->inDateRange($invoice->refused_at, $from, $to)) {
                    $rows->push([
                        'id' => $invoice->id,
                        'at' => $invoice->refused_at,
                        'type' => 'REFUSE',
                        'reference' => $invoice->invoice_number,
                        'amount' => $refuseAmount,
                        'direction' => 'credit',
                        'url' => route('gross.sales.show', $invoice),
                    ]);
                }

                $deliveryFee = (float) $invoice->shipping_fee;
                if ($deliveryFee > 0 && $this->inDateRange($invoice->refused_at, $from, $to)) {
                    $rows->push([
                        'id' => $invoice->id,
                        'at' => $invoice->refused_at->copy()->addSecond(),
                        'type' => 'DELIVERY_FEE',
                        'reference' => $invoice->invoice_number,
                        'amount' => $deliveryFee,
                        'direction' => 'credit',
                        'affects_balance' => false,
                        'url' => route('gross.sales.show', $invoice),
                    ]);
                }
            }

            if ($invoice->cod_collection_status === GrossSalesInvoice::COD_COLLECTED
                && $invoice->cod_collected_at !== null
                && (float) ($invoice->cod_collected_amount ?? 0) > 0) {
                /** @var GrossTreasuryVoucher|null $treasuryVoucher */
                $treasuryVoucher = $treasuryByInvoice->get($invoice->id);

                if ($treasuryVoucher !== null
                    && $this->inDateRange($treasuryVoucher->created_at ?? $treasuryVoucher->voucher_date, $from, $to)) {
                    $rows->push([
                        'id' => $treasuryVoucher->id,
                        'at' => $treasuryVoucher->created_at ?? $treasuryVoucher->voucher_date->startOfDay(),
                        'type' => 'TREASURY_RECEIPT',
                        'reference' => $treasuryVoucher->voucher_number,
                        'amount' => (float) $treasuryVoucher->amount,
                        'direction' => 'credit',
                        'url' => route('gross.treasury.show', $treasuryVoucher),
                    ]);
                } elseif ($treasuryVoucher === null
                    && $this->inDateRange($invoice->cod_collected_at, $from, $to)) {
                    $rows->push([
                        'id' => $invoice->id,
                        'at' => $invoice->cod_collected_at,
                        'type' => 'COLLECTION',
                        'reference' => $invoice->invoice_number,
                        'amount' => (float) $invoice->cod_collected_amount,
                        'direction' => 'credit',
                        'url' => route('gross.sales.show', $invoice),
                    ]);
                }
            }
        }

        return $rows->values();
    }

    /**
     * @return Collection<int, array{id: int, at: Carbon, type: string, reference: string, amount: float, direction: string, url: string|null}>
     */
    private function returnMovementRows(
        GrossShippingCompany $company,
        string $currency,
        ?Carbon $from,
        ?Carbon $to,
    ): Collection {
        $query = GrossStockMovement::query()
            ->whereIn('type', [
                GrossStockMovement::TYPE_SALE_RETURN_CLIENT,
                GrossStockMovement::TYPE_SALE_RETURN,
            ])
            ->where('ref_type', GrossShipmentAllocation::class)
            ->whereHas('shipmentAllocation', function ($allocation) use ($company, $currency): void {
                $allocation->where('shipping_company_id', $company->id)
                    ->whereHas('line.invoice', fn ($invoice) => $invoice
                        ->where('currency', $currency)
                        ->whereIn('status', [
                            GrossSalesInvoice::STATUS_CONFIRMED,
                            GrossSalesInvoice::STATUS_DELIVERED,
                            GrossSalesInvoice::STATUS_REFUSED,
                        ]));
            })
            ->with(['shipmentAllocation.line.invoice'])
            ->orderBy('created_at')
            ->orderBy('id');

        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query->get()
            ->filter(function (GrossStockMovement $movement): bool {
                if ($movement->type !== GrossStockMovement::TYPE_SALE_RETURN) {
                    return true;
                }

                $invoice = $movement->shipmentAllocation?->line?->invoice;
                if ($invoice === null) {
                    return true;
                }

                if ($invoice->cod_collected_at === null) {
                    return true;
                }

                $movementAt = $movement->created_at ?? now();

                return $movementAt->lt($invoice->cod_collected_at);
            })
            ->map(function (GrossStockMovement $movement): array {
                $line = $movement->shipmentAllocation?->line;
                $invoice = $line?->invoice;
                $qtyPieces = (int) ($line?->qty_pieces ?? 0);
                $lineTotal = (float) ($line?->line_total ?? 0);
                $returnedPieces = (int) $movement->qty_pieces;
                $amount = $qtyPieces > 0
                    ? round($lineTotal * ($returnedPieces / $qtyPieces), 2)
                    : 0.0;

                return [
                    'id' => $movement->id,
                    'invoice_id' => $invoice?->id,
                    'at' => $movement->created_at ?? now(),
                    'type' => 'RETURN',
                    'reference' => $invoice?->invoice_number ?? '—',
                    'amount' => $amount,
                    'direction' => 'credit',
                    'url' => $invoice ? route('gross.sales.show', $invoice) : null,
                ];
            });
    }

    private function collectedAmount(GrossSalesInvoice $invoice): float
    {
        if ($invoice->cod_collection_status !== GrossSalesInvoice::COD_COLLECTED) {
            return 0.0;
        }

        return (float) ($invoice->cod_collected_amount ?? 0);
    }

    private function collectedMerchandiseAmount(GrossSalesInvoice $invoice): float
    {
        if ($invoice->cod_collection_status !== GrossSalesInvoice::COD_COLLECTED) {
            return 0.0;
        }

        return min($this->collectedAmount($invoice), $this->courierMerchandiseAmount($invoice));
    }

    private function saleDebitAmount(GrossSalesInvoice $invoice): float
    {
        if ($invoice->status === GrossSalesInvoice::STATUS_REFUSED) {
            return $this->originalMerchandiseAmount($invoice);
        }

        if ((float) $invoice->returned_merchandise > 0) {
            return max(0, $this->courierMerchandiseAmount($invoice));
        }

        return $this->originalMerchandiseAmount($invoice);
    }

    private function returnRowsAreNettedInSale(GrossSalesInvoice $invoice): bool
    {
        return (float) $invoice->returned_merchandise > 0;
    }

    private function originalMerchandiseAmount(GrossSalesInvoice $invoice): float
    {
        return $invoice->originalMerchandiseAmount();
    }

    private function courierMerchandiseAmount(GrossSalesInvoice $invoice): float
    {
        return $invoice->courierMerchandiseAmount();
    }

    private function inDateRange(?Carbon $at, ?Carbon $from, ?Carbon $to): bool
    {
        if ($at === null) {
            return $from === null && $to === null;
        }

        if ($from !== null && $at->lt($from->copy()->startOfDay())) {
            return false;
        }

        if ($to !== null && $at->gt($to->copy()->endOfDay())) {
            return false;
        }

        return true;
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

    /**
     * @param  Collection<int, GrossSalesInvoice>  $invoices
     * @return Collection<int, GrossTreasuryVoucher>
     */
    private function courierCodTreasuryByInvoice(Collection $invoices): Collection
    {
        $ids = $invoices->pluck('id')->filter()->all();
        if ($ids === []) {
            return collect();
        }

        return GrossTreasuryVoucher::query()
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_COURIER_COD)
            ->whereIn('sales_invoice_id', $ids)
            ->get()
            ->keyBy('sales_invoice_id');
    }
}
