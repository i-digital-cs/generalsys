<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAssignment;
use App\Models\GeneralHouse\GhOrderAttempt;
use App\Models\User;
use App\Support\GeneralHouse\GhAssignmentResponsibility;
use App\Support\GeneralHouse\GhDriverMovementStatus;
use App\Support\GeneralHouse\GhDriverMovementType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Projects independent PICKUP and DELIVERY movements from orders + attempts + assignments.
 * Never merges two responsibilities into one row.
 */
class GhDriverMovementLogService
{
    /**
     * @param  array{
     *   q?: string|null,
     *   type?: string|null,
     *   status?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   per_page?: int,
     * }  $filters
     * @return LengthAwarePaginator<int, GhDriverMovement>
     */
    public function paginateForDriver(User $driver, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) request()->integer('page', 1));

        $all = $this->movementsForDriver($driver, $filters);
        $total = $all->count();
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @param  array{
     *   q?: string|null,
     *   type?: string|null,
     *   status?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     * }  $filters
     * @return Collection<int, GhDriverMovement>
     */
    public function movementsForDriver(User $driver, array $filters = []): Collection
    {
        $uid = (int) $driver->id;
        $referenceQuery = $this->normalizeReferenceQuery((string) ($filters['q'] ?? ''));

        $assignedOrderIds = GhOrderAssignment::query()
            ->where('driver_user_id', $uid)
            ->pluck('order_id')
            ->unique()
            ->all();

        $orders = GhOrder::query()
            ->with([
                'customer',
                'address.zone',
                'attempts' => fn ($q) => $q->orderByDesc('occurred_at')->orderByDesc('id'),
                'assignments' => fn ($q) => $q->orderByDesc('assigned_at')->orderByDesc('id'),
            ])
            ->where(function ($q) use ($uid, $assignedOrderIds) {
                $q->where('pickup_driver_id', $uid)
                    ->orWhere('delivery_driver_id', $uid);
                if ($assignedOrderIds !== []) {
                    $q->orWhereIn('id', $assignedOrderIds);
                }
            })
            ->when($referenceQuery !== '', function ($q) use ($referenceQuery) {
                // Server-side reference filter within this driver's orders only.
                $q->where('reference', 'like', '%'.$referenceQuery.'%');
            })
            ->get();

        $movements = collect();
        foreach ($orders as $order) {
            foreach ($this->expandOrder($order) as $movement) {
                if (! $this->driverOwnsMovement($movement, $uid)) {
                    continue;
                }
                $movements->push($movement);
            }
        }

        return $this->applyFilters($movements, $filters)
            ->sort(function (GhDriverMovement $a, GhDriverMovement $b) {
                $cmp = $a->sortTimestamp() <=> $b->sortTimestamp();
                if ($cmp !== 0) {
                    return $cmp;
                }
                // Same schedule / same order: PICKUP before DELIVERY — never merge.
                $typeRank = [
                    GhDriverMovementType::PICKUP => 0,
                    GhDriverMovementType::DELIVERY => 1,
                ];
                $typeCmp = ($typeRank[$a->type] ?? 9) <=> ($typeRank[$b->type] ?? 9);
                if ($typeCmp !== 0) {
                    return $typeCmp;
                }

                return $a->order->id <=> $b->order->id;
            })
            ->values();
    }

    /**
     * Recent completed movements for dashboard (separate cards).
     *
     * @return Collection<int, GhDriverMovement>
     */
    public function recentCompletedForDriver(User $driver, int $limit = 25): Collection
    {
        return $this->movementsForDriver($driver, ['status' => GhDriverMovementStatus::COMPLETED])
            ->sort(function (GhDriverMovement $a, GhDriverMovement $b) {
                $aAt = $a->completedAt ?? $a->sortTimestamp();
                $bAt = $b->completedAt ?? $b->sortTimestamp();
                $cmp = $bAt <=> $aAt;
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a->type, $b->type);
            })
            ->take($limit)
            ->values();
    }

    /**
     * Failed follow-up movements still open (separate by type).
     *
     * @return Collection<int, GhDriverMovement>
     */
    public function failedFollowupsForDriver(User $driver, int $limit = 50): Collection
    {
        return $this->movementsForDriver($driver, ['status' => GhDriverMovementStatus::FAILED])
            ->take($limit)
            ->values();
    }

    /**
     * @return list<GhDriverMovement>
     */
    public function expandOrder(GhOrder $order): array
    {
        $out = [];

        if ($this->hasPickupMovement($order)) {
            $out[] = $this->buildPickup($order);
        }
        if ($this->hasDeliveryMovement($order)) {
            $out[] = $this->buildDelivery($order);
        }

        return $out;
    }

    private function hasPickupMovement(GhOrder $order): bool
    {
        if ($order->pickup_driver_id !== null || $order->picked_up_at !== null) {
            return true;
        }

        return $order->relationLoaded('assignments')
            ? $order->assignments->contains('responsibility', GhAssignmentResponsibility::PICKUP)
            : GhOrderAssignment::query()
                ->where('order_id', $order->id)
                ->where('responsibility', GhAssignmentResponsibility::PICKUP)
                ->exists();
    }

    private function hasDeliveryMovement(GhOrder $order): bool
    {
        if ($order->delivery_driver_id !== null || $order->delivered_at !== null) {
            return true;
        }

        return $order->relationLoaded('assignments')
            ? $order->assignments->contains('responsibility', GhAssignmentResponsibility::DELIVERY)
            : GhOrderAssignment::query()
                ->where('order_id', $order->id)
                ->where('responsibility', GhAssignmentResponsibility::DELIVERY)
                ->exists();
    }

    private function buildPickup(GhOrder $order): GhDriverMovement
    {
        $attempts = $this->attemptsOfType($order, GhDriverMovementType::PICKUP);
        $history = $this->assignmentHistory($order, GhAssignmentResponsibility::PICKUP);

        return new GhDriverMovement(
            order: $order,
            type: GhDriverMovementType::PICKUP,
            status: $this->pickupStatus($order, $attempts),
            driverUserId: $order->pickup_driver_id !== null ? (int) $order->pickup_driver_id : ($history[0]['driver_user_id'] ?? null),
            scheduledAt: $order->pickup_starts_at,
            completedAt: $order->picked_up_at,
            attempts: $attempts,
            assignmentHistory: $history,
        );
    }

    private function buildDelivery(GhOrder $order): GhDriverMovement
    {
        $attempts = $this->attemptsOfType($order, GhDriverMovementType::DELIVERY);
        $history = $this->assignmentHistory($order, GhAssignmentResponsibility::DELIVERY);
        $scheduled = $order->invoice_issued_at ?? $order->pickup_ends_at;

        return new GhDriverMovement(
            order: $order,
            type: GhDriverMovementType::DELIVERY,
            status: $this->deliveryStatus($order, $attempts),
            driverUserId: $order->delivery_driver_id !== null ? (int) $order->delivery_driver_id : ($history[0]['driver_user_id'] ?? null),
            scheduledAt: $scheduled,
            completedAt: $order->delivered_at,
            attempts: $attempts,
            assignmentHistory: $history,
        );
    }

    /** @return Collection<int, GhOrderAttempt> */
    private function attemptsOfType(GhOrder $order, string $type): Collection
    {
        $all = $order->relationLoaded('attempts')
            ? $order->attempts
            : $order->attempts()->orderByDesc('occurred_at')->orderByDesc('id')->get();

        return $all->where('attempt_type', $type)->values();
    }

    /**
     * @return list<array{driver_user_id: int, assigned_at: ?Carbon, unassigned_at: ?Carbon, is_current: bool}>
     */
    private function assignmentHistory(GhOrder $order, string $responsibility): array
    {
        $rows = $order->relationLoaded('assignments')
            ? $order->assignments->where('responsibility', $responsibility)
            : GhOrderAssignment::query()
                ->where('order_id', $order->id)
                ->where('responsibility', $responsibility)
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->get();

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'driver_user_id' => (int) $row->driver_user_id,
                'assigned_at' => $row->assigned_at,
                'unassigned_at' => $row->unassigned_at,
                'is_current' => (bool) $row->is_current,
            ];
        }

        return $history;
    }

    private function pickupStatus(GhOrder $order, Collection $attempts): string
    {
        if ($order->picked_up_at !== null) {
            return GhDriverMovementStatus::COMPLETED;
        }
        if ($order->status === GhOrderStatus::CANCELLED) {
            return GhDriverMovementStatus::CANCELLED;
        }
        if ($order->status === GhOrderStatus::PICKUP && $attempts->isNotEmpty()) {
            return GhDriverMovementStatus::FAILED;
        }
        if ($order->status === GhOrderStatus::PICKUP) {
            return GhDriverMovementStatus::READY;
        }
        if (in_array($order->status, [GhOrderStatus::WORKING, GhOrderStatus::DELIVERY, GhOrderStatus::COMPLETED], true)) {
            // Picked up without timestamp edge — treat as completed phase.
            return GhDriverMovementStatus::COMPLETED;
        }

        return GhDriverMovementStatus::SCHEDULED;
    }

    private function deliveryStatus(GhOrder $order, Collection $attempts): string
    {
        if ($order->delivered_at !== null) {
            return GhDriverMovementStatus::COMPLETED;
        }
        if ($order->status === GhOrderStatus::CANCELLED) {
            return GhDriverMovementStatus::CANCELLED;
        }
        if ($order->status === GhOrderStatus::DELIVERY && $attempts->isNotEmpty()) {
            return GhDriverMovementStatus::FAILED;
        }
        if ($order->status === GhOrderStatus::DELIVERY) {
            return GhDriverMovementStatus::READY;
        }
        if ($order->status === GhOrderStatus::COMPLETED) {
            return GhDriverMovementStatus::COMPLETED;
        }

        return GhDriverMovementStatus::SCHEDULED;
    }

    private function driverOwnsMovement(GhDriverMovement $movement, int $uid): bool
    {
        if ($movement->driverUserId === $uid) {
            return true;
        }
        foreach ($movement->assignmentHistory as $row) {
            if ((int) $row['driver_user_id'] === $uid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, GhDriverMovement>  $movements
     * @param  array{q?: string|null, type?: string|null, status?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return Collection<int, GhDriverMovement>
     */
    private function applyFilters(Collection $movements, array $filters): Collection
    {
        $referenceQuery = $this->normalizeReferenceQuery((string) ($filters['q'] ?? ''));
        if ($referenceQuery !== '') {
            $movements = $movements->filter(function (GhDriverMovement $m) use ($referenceQuery) {
                return $this->referenceMatches((string) $m->order->reference, $referenceQuery);
            })->values();
        }

        $type = strtoupper(trim((string) ($filters['type'] ?? '')));
        if ($type !== '' && GhDriverMovementType::isValid($type)) {
            $movements = $movements->where('type', $type)->values();
        }

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, GhDriverMovementStatus::ALL, true)) {
            $movements = $movements->where('status', $status)->values();
        }

        $tz = (string) config('general_house.timezone', 'Asia/Dubai');
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($from !== '') {
            $fromAt = Carbon::parse($from, $tz)->startOfDay()->utc();
            $movements = $movements->filter(function (GhDriverMovement $m) use ($fromAt) {
                return $m->sortTimestamp()->greaterThanOrEqualTo($fromAt);
            })->values();
        }
        if ($to !== '') {
            $toAt = Carbon::parse($to, $tz)->endOfDay()->utc();
            $movements = $movements->filter(function (GhDriverMovement $m) use ($toAt) {
                return $m->sortTimestamp()->lessThanOrEqualTo($toAt);
            })->values();
        }

        return $movements;
    }

    private function normalizeReferenceQuery(string $raw): string
    {
        $q = trim($raw);
        if ($q === '') {
            return '';
        }

        // Keep letters, digits, and hyphens; collapse whitespace.
        $q = preg_replace('/\s+/', '', $q) ?? $q;
        $q = preg_replace('/[^A-Za-z0-9\-]/', '', $q) ?? $q;

        return strtoupper($q);
    }

    private function referenceMatches(string $reference, string $query): bool
    {
        $ref = strtoupper($reference);
        if (str_contains($ref, $query)) {
            return true;
        }

        if (preg_match('/^\d+$/', $query) !== 1) {
            return false;
        }

        // Numeric search: match padded or unpadded trailing order number.
        if (preg_match('/(\d+)$/', $ref, $m) !== 1) {
            return false;
        }
        $digits = $m[1];
        $needle = ltrim($query, '0');
        $hay = ltrim($digits, '0');

        return $digits === $query
            || ($needle !== '' && ($hay === $needle || str_ends_with($digits, $query)));
    }
}
