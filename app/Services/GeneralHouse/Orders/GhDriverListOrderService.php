<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists a driver's personal ordering of the actionable pickup / delivery lists.
 */
class GhDriverListOrderService
{
    public function __construct(private readonly GhDriverWorkspaceService $workspace) {}

    /**
     * @param  list<int|string>  $orderIds  Desired order (first = position 0)
     *
     * @throws AuthorizationException when an order is not assigned to this driver for the list
     * @throws ValidationException when an order is not currently on the actionable list
     */
    /**
     * @param  list<int|string>  $orderIds
     * @param  list<int|string>|null  $previousOrderIds  Optimistic concurrency baseline from the client
     */
    public function reorder(User $driver, string $listType, array $orderIds, ?array $previousOrderIds = null): void
    {
        $ids = array_values(array_unique(array_map('intval', $orderIds)));
        $column = $this->ownerColumn($listType);
        $uid = (int) $driver->id;

        $owned = GhOrder::query()->whereIn('id', $ids)->where($column, $uid)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($owned) !== count($ids)) {
            throw new AuthorizationException;
        }

        $actionable = $this->actionableIds($driver, $listType);
        if (array_diff($ids, $actionable) !== []) {
            throw ValidationException::withMessages(['order_ids' => [__('gh.driver.reorder_invalid')]]);
        }

        if ($previousOrderIds !== null) {
            $expected = array_values(array_map('intval', $previousOrderIds));
            $current = $this->currentOrderedIds($uid, $listType, $actionable);
            if ($expected !== [] && $expected !== $current) {
                throw ValidationException::withMessages([
                    'order_ids' => [__('gh.driver.reorder_stale')],
                ]);
            }
        }

        // Tasks missing from the payload keep their current relative order after the submitted ones.
        $final = array_merge($ids, array_values(array_diff($actionable, $ids)));

        DB::transaction(function () use ($uid, $listType, $final) {
            $now = now();
            DB::table('gh_driver_list_positions')
                ->where('driver_user_id', $uid)
                ->where('list_type', $listType)
                ->whereNotIn('order_id', $final)
                ->delete();

            DB::table('gh_driver_list_positions')->upsert(
                array_map(fn (int $orderId, int $position) => [
                    'driver_user_id' => $uid,
                    'list_type' => $listType,
                    'order_id' => $orderId,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $final, array_keys($final)),
                ['driver_user_id', 'list_type', 'order_id'],
                ['position', 'updated_at']
            );
        });
    }

    private function ownerColumn(string $listType): string
    {
        return match ($listType) {
            GhDriverWorkspaceService::LIST_PICKUP => 'pickup_driver_id',
            GhDriverWorkspaceService::LIST_DELIVERY => 'delivery_driver_id',
            default => throw new \InvalidArgumentException('Unknown driver list type.'),
        };
    }

    /** @return list<int> */
    private function actionableIds(User $driver, string $listType): array
    {
        $query = $listType === GhDriverWorkspaceService::LIST_PICKUP
            ? $this->workspace->actionablePickupsQuery($driver)
            : $this->workspace->actionableDeliveriesQuery($driver);

        return $query->pluck('gh_orders.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Current persisted order for actionable tasks (unpositioned tasks keep actionable query order).
     *
     * @param  list<int>  $actionable
     * @return list<int>
     */
    private function currentOrderedIds(int $driverUserId, string $listType, array $actionable): array
    {
        if ($actionable === []) {
            return [];
        }

        $positions = DB::table('gh_driver_list_positions')
            ->where('driver_user_id', $driverUserId)
            ->where('list_type', $listType)
            ->whereIn('order_id', $actionable)
            ->orderBy('position')
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_merge($positions, array_diff($actionable, $positions)));
    }
}
