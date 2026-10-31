<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAttempt;
use App\Models\User;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model for the driver workspace. Status changes remain in GhOrderStateService.
 */
class GhDriverWorkspaceService
{
    /**
     * @return array{
     *   today_pickups: Collection<int, GhOrder>,
     *   upcoming_pickups: Collection<int, GhOrder>,
     *   pending_deliveries: Collection<int, GhOrder>,
     *   completed: Collection<int, GhOrder>,
     *   failed_followups: Collection<int, GhOrder>,
     *   business_date: string,
     *   timezone: string,
     * }
     */
    public function dashboard(User $driver): array
    {
        $tz = (string) config('general_house.timezone', 'Asia/Dubai');
        $now = Carbon::now($tz);
        $dayStart = $now->copy()->startOfDay()->utc();
        $dayEnd = $now->copy()->endOfDay()->utc();
        $uid = (int) $driver->id;

        $with = ['customer', 'address.zone', 'invoice'];

        $todayPickups = GhOrder::query()
            ->with($with)
            ->where('pickup_driver_id', $uid)
            ->where('status', GhOrderStatus::PICKUP)
            ->whereBetween('pickup_starts_at', [$dayStart, $dayEnd])
            ->orderBy('pickup_starts_at')
            ->limit(50)
            ->get();

        $upcomingPickups = GhOrder::query()
            ->with($with)
            ->where('pickup_driver_id', $uid)
            ->whereIn('status', [GhOrderStatus::LATER, GhOrderStatus::PICKUP])
            ->where('pickup_starts_at', '>', $dayEnd)
            ->orderBy('pickup_starts_at')
            ->limit(50)
            ->get();

        // Also show PICKUP tasks not in "today" window but still actionable (past window / earlier).
        $otherActivePickups = GhOrder::query()
            ->with($with)
            ->where('pickup_driver_id', $uid)
            ->where('status', GhOrderStatus::PICKUP)
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->where('pickup_starts_at', '<', $dayStart)
                    ->orWhere('pickup_starts_at', '>', $dayEnd);
            })
            ->orderBy('pickup_starts_at')
            ->limit(50)
            ->get();

        $todayPickups = $todayPickups
            ->merge($otherActivePickups->filter(fn (GhOrder $o) => $o->pickup_starts_at !== null && $o->pickup_starts_at->lt($dayStart)))
            ->unique('id')
            ->sortBy('pickup_starts_at')
            ->values();

        $pendingDeliveries = GhOrder::query()
            ->with($with)
            ->where('delivery_driver_id', $uid)
            ->where('status', GhOrderStatus::DELIVERY)
            ->orderByDesc('invoice_issued_at')
            ->limit(50)
            ->get();

        $completed = GhOrder::query()
            ->with($with)
            ->where('status', GhOrderStatus::COMPLETED)
            ->where(function ($q) use ($uid) {
                $q->where('pickup_driver_id', $uid)
                    ->orWhere('delivery_driver_id', $uid);
            })
            ->orderByDesc('delivered_at')
            ->limit(25)
            ->get();

        $failedOrderIds = GhOrderAttempt::query()
            ->where('outcome', 'FAILED')
            ->where('occurred_at', '>=', $now->copy()->subDays(7)->utc())
            ->whereIn('order_id', function ($q) use ($uid) {
                $q->select('id')
                    ->from('gh_orders')
                    ->where(function ($inner) use ($uid) {
                        $inner->where('pickup_driver_id', $uid)
                            ->orWhere('delivery_driver_id', $uid);
                    })
                    ->whereIn('status', [GhOrderStatus::PICKUP, GhOrderStatus::DELIVERY]);
            })
            ->pluck('order_id')
            ->unique()
            ->all();

        $failedFollowups = $failedOrderIds === []
            ? collect()
            : GhOrder::query()
                ->with($with)
                ->whereIn('id', $failedOrderIds)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get();

        return [
            'today_pickups' => $todayPickups,
            'upcoming_pickups' => $upcomingPickups,
            'pending_deliveries' => $pendingDeliveries,
            'completed' => $completed,
            'failed_followups' => $failedFollowups,
            'business_date' => $now->toDateString(),
            'timezone' => $tz,
        ];
    }

    public function assertCanOpenTask(User $driver, GhOrder $order, string $taskType): void
    {
        $uid = (int) $driver->id;
        if ($taskType === 'pickup') {
            if ((int) $order->pickup_driver_id !== $uid) {
                abort(403);
            }

            return;
        }
        if ($taskType === 'delivery') {
            if ((int) $order->delivery_driver_id !== $uid) {
                abort(403);
            }

            return;
        }
        abort(404);
    }
}
