<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAssignment;
use App\Models\User;
use App\Support\GeneralHouse\GhAssignmentResponsibility;
use App\Support\GeneralHouse\GhOrderEventType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhOrderAssignmentService
{
    public function __construct(
        private readonly GhDriverEligibilityService $drivers,
        private readonly GhOrderEventService $events,
    ) {}

    public function assignPickup(GhOrder $order, int $driverUserId, User $actor): GhOrder
    {
        return $this->assign($order, GhAssignmentResponsibility::PICKUP, $driverUserId, $actor);
    }

    public function assignDelivery(GhOrder $order, ?int $driverUserId, User $actor): GhOrder
    {
        if ($driverUserId === null) {
            return $this->clear($order, GhAssignmentResponsibility::DELIVERY, $actor);
        }

        return $this->assign($order, GhAssignmentResponsibility::DELIVERY, $driverUserId, $actor);
    }

    public function assign(GhOrder $order, string $responsibility, int $driverUserId, User $actor): GhOrder
    {
        if (! in_array($responsibility, GhAssignmentResponsibility::ALL, true)) {
            throw ValidationException::withMessages([
                'responsibility' => [__('gh.errors.assignment_invalid')],
            ]);
        }

        $driver = $this->drivers->assertEligible(
            $driverUserId,
            $responsibility === GhAssignmentResponsibility::PICKUP ? 'pickup_driver_id' : 'delivery_driver_id'
        );

        return DB::transaction(function () use ($order, $responsibility, $driver, $actor) {
            $order = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = GhOrderAssignment::query()
                ->where('order_id', $order->id)
                ->where('responsibility', $responsibility)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            $now = Carbon::now();
            $column = $responsibility === GhAssignmentResponsibility::PICKUP
                ? 'pickup_driver_id'
                : 'delivery_driver_id';

            if ($current !== null && (int) $current->driver_user_id === (int) $driver->id) {
                return $order->refresh();
            }

            $wasReassign = $current !== null;
            if ($current !== null) {
                $current->update([
                    'is_current' => false,
                    'unassigned_at' => $now,
                ]);
            }

            GhOrderAssignment::query()->create([
                'order_id' => $order->id,
                'driver_user_id' => $driver->id,
                'responsibility' => $responsibility,
                'assigned_by' => $actor->id,
                'assigned_at' => $now,
                'unassigned_at' => null,
                'is_current' => true,
            ]);

            $order->update([$column => $driver->id, 'updated_by' => $actor->id]);

            $this->events->record(
                $order,
                $wasReassign ? GhOrderEventType::DRIVER_REASSIGNED : GhOrderEventType::DRIVER_ASSIGNED,
                $actor,
                null,
                null,
                [
                    'responsibility' => $responsibility,
                    'driver_user_id' => $driver->id,
                    'previous_driver_user_id' => $current?->driver_user_id,
                ],
                $now,
            );

            return $order->refresh();
        });
    }

    public function clear(GhOrder $order, string $responsibility, User $actor): GhOrder
    {
        return DB::transaction(function () use ($order, $responsibility, $actor) {
            $order = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = GhOrderAssignment::query()
                ->where('order_id', $order->id)
                ->where('responsibility', $responsibility)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                return $order;
            }

            $now = Carbon::now();
            $current->update([
                'is_current' => false,
                'unassigned_at' => $now,
            ]);

            $column = $responsibility === GhAssignmentResponsibility::PICKUP
                ? 'pickup_driver_id'
                : 'delivery_driver_id';

            $order->update([$column => null, 'updated_by' => $actor->id]);

            $this->events->record(
                $order,
                GhOrderEventType::DRIVER_REASSIGNED,
                $actor,
                null,
                null,
                [
                    'responsibility' => $responsibility,
                    'driver_user_id' => null,
                    'previous_driver_user_id' => $current->driver_user_id,
                    'cleared' => true,
                ],
                $now,
            );

            return $order->refresh();
        });
    }
}
