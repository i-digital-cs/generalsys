<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockMovement;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ShippingReturnsReportService
{
    private const MAX_PER_PAGE = 50;

    /**
     * @param  array{from?: string, to?: string}  $filters
     * @return array{event_count: int, total_pieces: int}
     */
    public function totals(GrossShippingCompany $company, array $filters = []): array
    {
        $query = $this->baseQuery($company, $filters);

        return [
            'event_count' => (int) (clone $query)->count(),
            'total_pieces' => (int) (clone $query)->sum('qty_pieces'),
        ];
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     */
    public function paginate(GrossShippingCompany $company, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->baseQuery($company, $filters)
            ->with([
                'item.images',
                'creator:id,name_ar,name_en',
                'shipmentAllocation.line.invoice:id,invoice_number,customer_name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min($perPage, self::MAX_PER_PAGE))
            ->withQueryString();
    }

    public function returnKind(GrossStockMovement $movement): string
    {
        if ($movement->type === GrossStockMovement::TYPE_REFUSE_RETURN) {
            return 'REFUSE';
        }

        if ($movement->type === GrossStockMovement::TYPE_SALE_RETURN_CLIENT) {
            $allocation = $movement->shipmentAllocation;
            if ($allocation !== null) {
                $maxReturned = (int) $allocation->qty_shipped_pieces
                    - (int) $allocation->qty_delivered_pieces
                    - (int) $allocation->qty_lost_pieces;

                if ((int) $allocation->qty_returned_pieces >= $maxReturned && $maxReturned > 0) {
                    return 'REFUSE';
                }
            }
        }

        return 'PARTIAL';
    }

    /**
     * Where pieces sit after this return event is recorded.
     */
    public function locationAfterReturn(GrossStockMovement $movement): string
    {
        if ($movement->type === GrossStockMovement::TYPE_SALE_RETURN_CLIENT) {
            return 'WITH_COURIER';
        }

        if (in_array($movement->type, [
            GrossStockMovement::TYPE_SALE_RETURN,
            GrossStockMovement::TYPE_REFUSE_RETURN,
        ], true)) {
            return 'WAREHOUSE';
        }

        return 'UNKNOWN';
    }

    /**
     * Current allocation snapshot for the invoice line linked to this return event.
     *
     * @return array{with_courier: int, delivered: int, returned_total: int}|null
     */
    public function currentAllocationSnapshot(GrossStockMovement $movement): ?array
    {
        $allocation = $movement->relationLoaded('shipmentAllocation')
            ? $movement->shipmentAllocation
            : $movement->shipmentAllocation()->first();

        if ($allocation === null) {
            return null;
        }

        return [
            'with_courier' => (int) $allocation->qty_with_courier_pieces,
            'delivered' => (int) $allocation->qty_delivered_pieces,
            'returned_total' => (int) $allocation->qty_returned_pieces,
        ];
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     * @return Builder<GrossStockMovement>
     */
    private function baseQuery(GrossShippingCompany $company, array $filters): Builder
    {
        $query = GrossStockMovement::query()
            ->whereIn('type', [
                GrossStockMovement::TYPE_SALE_RETURN_CLIENT,
                GrossStockMovement::TYPE_REFUSE_RETURN,
            ])
            ->where('ref_type', GrossShipmentAllocation::class)
            ->whereHas('shipmentAllocation', function (Builder $allocation) use ($company): void {
                $allocation->where('shipping_company_id', $company->id);
            });

        $from = $this->parseDate($filters['from'] ?? null);
        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        $to = $this->parseDate($filters['to'] ?? null);
        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
