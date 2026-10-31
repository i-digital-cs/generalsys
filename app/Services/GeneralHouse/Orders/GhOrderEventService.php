<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

class GhOrderEventService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        GhOrder $order,
        string $eventType,
        ?User $actor = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?array $payload = null,
        ?Carbon $occurredAt = null,
    ): GhOrderEvent {
        $now = Carbon::now();

        return GhOrderEvent::query()->create([
            'order_id' => $order->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actor?->id,
            'occurred_at' => $occurredAt ?? $now,
            'recorded_at' => $now,
            'payload' => $payload,
        ]);
    }
}
