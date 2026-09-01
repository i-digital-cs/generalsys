<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LowStockAlertService
{
    public function count(): int
    {
        return $this->baseQuery()->count();
    }

    /**
     * Active items at or below reorder level (on hand), newest codes last.
     *
     * @return Collection<int, GrossItem>
     */
    public function items(int $limit = 10): Collection
    {
        return $this->baseQuery()
            ->with('balance')
            ->orderBy('code')
            ->limit(max(1, min($limit, 50)))
            ->get();
    }

    /**
     * @return Builder<GrossItem>
     */
    private function baseQuery(): Builder
    {
        return GrossItem::query()
            ->where('status', GrossItem::STATUS_ACTIVE)
            ->where(function (Builder $inner): void {
                $inner->whereDoesntHave('balance')
                    ->orWhereHas('balance', function (Builder $balance): void {
                        $balance->whereRaw(
                            'qty_on_hand_pieces <= (select reorder_level from gross_items where id = gross_stock_balances.item_id)'
                        );
                    });
            });
    }
}
