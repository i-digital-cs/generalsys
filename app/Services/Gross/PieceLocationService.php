<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;

/**
 * Where each piece lives: warehouse (on hand) vs with courier (by company) vs lost.
 */
class PieceLocationService
{
    /**
     * @param  iterable<int>  $itemIds
     * @return array<int, array{
     *     awaiting_delivery: int,
     *     refused_with_courier: int,
     *     total_with_courier: int
     * }>
     */
    public function courierSummaryByItemIds(iterable $itemIds): array
    {
        $out = [];
        foreach ($this->activeCourierAllocations($itemIds) as $allocation) {
            $itemId = (int) ($allocation->line?->item_id ?? 0);
            if ($itemId < 1) {
                continue;
            }

            $awaiting = $allocation->deliverableWithCourier();
            $refused = $allocation->returnedPendingHandover();

            $out[$itemId] ??= [
                'awaiting_delivery' => 0,
                'refused_with_courier' => 0,
                'total_with_courier' => 0,
            ];
            $out[$itemId]['awaiting_delivery'] += $awaiting;
            $out[$itemId]['refused_with_courier'] += $refused;
            $out[$itemId]['total_with_courier'] += (int) $allocation->qty_with_courier_pieces;
        }

        return $out;
    }

    /**
     * @param  iterable<int>  $itemIds
     * @return array<int, list<array{
     *     company_id: int|null,
     *     company_name: string,
     *     qty: int,
     *     awaiting_delivery: int,
     *     refused_with_courier: int,
     *     invoices: list<array{
     *         invoice_id: int,
     *         invoice_number: string,
     *         customer_name: string,
     *         qty: int,
     *         awaiting_delivery: int,
     *         refused_with_courier: int,
     *         invoice_status: string
     *     }>
     * }>>
     */
    public function courierBreakdownByItemIds(iterable $itemIds): array
    {
        $ids = collect($itemIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($this->activeCourierAllocations($ids) as $allocation) {
            $itemId = (int) ($allocation->line?->item_id ?? 0);
            if ($itemId < 1) {
                continue;
            }

            $invoice = $allocation->line?->invoice;
            $companyName = $allocation->shippingCompany?->name
                ?? $invoice?->shipping_company_name
                ?? '—';

            $companyId = $allocation->shipping_company_id !== null
                ? (int) $allocation->shipping_company_id
                : null;

            $awaiting = $allocation->deliverableWithCourier();
            $refused = $allocation->returnedPendingHandover();
            $qty = (int) $allocation->qty_with_courier_pieces;

            $bucketKey = ($companyId ?? 0).':'.$companyName;
            $out[$itemId][$bucketKey] ??= [
                'company_id' => $companyId,
                'company_name' => $companyName,
                'qty' => 0,
                'awaiting_delivery' => 0,
                'refused_with_courier' => 0,
                'invoices' => [],
            ];
            $out[$itemId][$bucketKey]['qty'] += $qty;
            $out[$itemId][$bucketKey]['awaiting_delivery'] += $awaiting;
            $out[$itemId][$bucketKey]['refused_with_courier'] += $refused;

            if ($invoice !== null) {
                $invoiceId = (int) $invoice->id;
                $out[$itemId][$bucketKey]['invoices'][$invoiceId] ??= [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'customer_name' => (string) $invoice->customer_name,
                    'qty' => 0,
                    'awaiting_delivery' => 0,
                    'refused_with_courier' => 0,
                    'invoice_status' => (string) $invoice->status,
                ];
                $out[$itemId][$bucketKey]['invoices'][$invoiceId]['qty'] += $qty;
                $out[$itemId][$bucketKey]['invoices'][$invoiceId]['awaiting_delivery'] += $awaiting;
                $out[$itemId][$bucketKey]['invoices'][$invoiceId]['refused_with_courier'] += $refused;
            }
        }

        foreach ($out as $itemId => $buckets) {
            foreach ($buckets as $key => $bucket) {
                $invoices = array_values($bucket['invoices']);
                usort($invoices, fn (array $a, array $b): int => strcmp($a['invoice_number'], $b['invoice_number']));
                $buckets[$key]['invoices'] = $invoices;
            }
            $out[$itemId] = array_values($buckets);
            usort($out[$itemId], fn (array $a, array $b): int => strcmp($a['company_name'], $b['company_name']));
        }

        return $out;
    }

    public function totalTrackedPieces(?GrossStockBalance $balance): int
    {
        if ($balance === null) {
            return 0;
        }

        return (int) $balance->qty_on_hand_pieces
            + (int) $balance->qty_with_courier_pieces
            + (int) $balance->qty_lost_pieces;
    }

    /**
     * Active allocations: pieces still physically with this courier (confirmed invoices).
     *
     * @return list<array{
     *     item_code: string,
     *     item_name: string,
     *     invoice_number: string,
     *     invoice_id: int,
     *     customer_name: string,
     *     qty: int,
     *     awaiting_delivery: int,
     *     refused_with_courier: int
     * }>
     */
    public function activeWithCourierForCompany(GrossShippingCompany $company): array
    {
        return GrossShipmentAllocation::query()
            ->where('shipping_company_id', $company->id)
            ->where('qty_with_courier_pieces', '>', 0)
            ->whereHas('line.invoice', fn ($invoice) => $invoice->whereIn('status', [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ]))
            ->with(['line.item:id,code,name', 'line.invoice:id,invoice_number,customer_name,status'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(function (GrossShipmentAllocation $allocation): array {
                $line = $allocation->line;
                $invoice = $line?->invoice;
                $item = $line?->item;

                return [
                    'item_code' => (string) ($item?->code ?? '—'),
                    'item_name' => (string) ($item?->name ?? '—'),
                    'invoice_number' => (string) ($invoice?->invoice_number ?? '—'),
                    'invoice_id' => (int) ($invoice?->id ?? 0),
                    'customer_name' => (string) ($invoice?->customer_name ?? '—'),
                    'qty' => (int) $allocation->qty_with_courier_pieces,
                    'awaiting_delivery' => $allocation->deliverableWithCourier(),
                    'refused_with_courier' => $allocation->returnedPendingHandover(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int>  $itemIds
     * @return iterable<int, GrossShipmentAllocation>
     */
    private function activeCourierAllocations(iterable $itemIds): iterable
    {
        $ids = collect($itemIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return GrossShipmentAllocation::query()
            ->where('qty_with_courier_pieces', '>', 0)
            ->whereHas('line', fn ($line) => $line->whereIn('item_id', $ids))
            ->whereHas('line.invoice', fn ($invoice) => $invoice->whereIn('status', [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ]))
            ->with([
                'shippingCompany:id,name',
                'line:id,item_id,sales_invoice_id',
                'line.invoice:id,invoice_number,customer_name,shipping_company_name,status',
            ])
            ->get(['id', 'sales_invoice_line_id', 'shipping_company_id', 'qty_with_courier_pieces', 'qty_returned_pieces', 'qty_returned_received_pieces', 'qty_delivered_pieces', 'qty_lost_pieces', 'qty_shipped_pieces']);
    }
}
