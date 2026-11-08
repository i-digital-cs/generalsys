<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAttempt;
use App\Support\GeneralHouse\GhDriverMovementType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One independent driver movement (PICKUP or DELIVERY) — never a combined row.
 */
final class GhDriverMovement
{
    /**
     * @param  Collection<int, GhOrderAttempt>  $attempts
     * @param  list<array{driver_user_id: int, assigned_at: ?Carbon, unassigned_at: ?Carbon, is_current: bool}>  $assignmentHistory
     */
    public function __construct(
        public readonly GhOrder $order,
        public readonly string $type,
        public readonly string $status,
        public readonly ?int $driverUserId,
        public readonly ?Carbon $scheduledAt,
        public readonly ?Carbon $completedAt,
        public readonly Collection $attempts,
        public readonly array $assignmentHistory = [],
    ) {
        if (! GhDriverMovementType::isValid($this->type)) {
            throw new \InvalidArgumentException('Invalid driver movement type: '.$this->type);
        }
    }

    public function key(): string
    {
        return $this->order->id.':'.$this->type;
    }

    public function taskType(): string
    {
        return $this->type === GhDriverMovementType::DELIVERY ? 'delivery' : 'pickup';
    }

    public function sortTimestamp(): Carbon
    {
        return $this->scheduledAt
            ?? $this->completedAt
            ?? $this->order->updated_at
            ?? Carbon::createFromTimestamp(0);
    }
}
