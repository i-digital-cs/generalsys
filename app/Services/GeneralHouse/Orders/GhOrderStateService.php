<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Foundational transition interface for GH-3.
 * Controllers must not overwrite status directly — only through this service.
 * GH-4+ will add pickup confirmation, scheduler promotion, invoice/delivery transitions.
 */
class GhOrderStateService
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        GhOrderStatus::LATER => [GhOrderStatus::PICKUP, GhOrderStatus::CANCELLED],
        GhOrderStatus::PICKUP => [GhOrderStatus::WORKING, GhOrderStatus::LATER, GhOrderStatus::CANCELLED],
        GhOrderStatus::WORKING => [GhOrderStatus::DELIVERY, GhOrderStatus::CANCELLED],
        GhOrderStatus::DELIVERY => [GhOrderStatus::COMPLETED, GhOrderStatus::CANCELLED],
        GhOrderStatus::COMPLETED => [],
        GhOrderStatus::CANCELLED => [],
    ];

    public function __construct(
        private readonly GhOrderEventService $events,
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    /**
     * Reserved for GH-4+. Throws until a concrete transition handler is implemented.
     */
    public function transition(GhOrder $order, string $toStatus, User $actor, string $reason): GhOrder
    {
        if (! in_array($toStatus, GhOrderStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.order_status_invalid')],
            ]);
        }

        if (! $this->canTransition($order->status, $toStatus)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.order_transition_denied')],
            ]);
        }

        // GH-3: no operational transitions yet (pickup confirm / scheduler / invoice / delivery).
        throw ValidationException::withMessages([
            'status' => [__('gh.errors.order_transition_not_implemented', ['reason' => $reason])],
        ]);
    }

    /**
     * Internal: set initial status at create (not a public controller path).
     */
    public function applyInitialStatus(GhOrder $order, string $status, User $actor): void
    {
        if (! in_array($status, [GhOrderStatus::LATER, GhOrderStatus::PICKUP], true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.order_status_invalid')],
            ]);
        }

        DB::transaction(function () use ($order, $status, $actor) {
            $from = $order->status;
            if ($from === $status) {
                return;
            }
            $order->update(['status' => $status]);
            $this->events->record(
                $order,
                GhOrderEventType::STATUS_CHANGED,
                $actor,
                $from,
                $status,
                ['source' => 'initial_eligibility'],
            );
        });
    }
}
