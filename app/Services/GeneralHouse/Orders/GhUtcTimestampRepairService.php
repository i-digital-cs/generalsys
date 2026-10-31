<?php

namespace App\Services\GeneralHouse\Orders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time, idempotent correction: legacy GH TIMESTAMP digits were Dubai/Cairo
 * wall-clock values written into UTC TIMESTAMP columns. Convert to true UTC.
 *
 * Does not touch Attendance / Payroll / Gross tables or Laravel created_at/updated_at.
 */
class GhUtcTimestampRepairService
{
    public const MARKER = 'utc_timestamp_repair_v1';

    /** Event types whose occurred_at was parsed in the order business timezone. */
    private const ORDER_TZ_EVENT_TYPES = [
        'PICKUP_CONFIRMED',
        'DELIVERY_CONFIRMED',
        'PICKUP_FAILED',
        'DELIVERY_FAILED',
    ];

    /**
     * @return array{
     *   already_applied: bool,
     *   orders: list<array<string, mixed>>,
     *   events: list<array<string, mixed>>,
     *   assignments: list<array<string, mixed>>,
     *   attempts: list<array<string, mixed>>,
     * }
     */
    public function preview(): array
    {
        return $this->run(dryRun: true);
    }

    /**
     * @return array{
     *   already_applied: bool,
     *   orders: list<array<string, mixed>>,
     *   events: list<array<string, mixed>>,
     *   assignments: list<array<string, mixed>>,
     *   attempts: list<array<string, mixed>>,
     * }
     */
    public function apply(): array
    {
        return $this->run(dryRun: false);
    }

    public function alreadyApplied(): bool
    {
        if (! Schema::hasTable('gh_sequences')) {
            return false;
        }

        return DB::table('gh_sequences')->where('name', self::MARKER)->exists();
    }

    /**
     * @return array{
     *   already_applied: bool,
     *   orders: list<array<string, mixed>>,
     *   events: list<array<string, mixed>>,
     *   assignments: list<array<string, mixed>>,
     *   attempts: list<array<string, mixed>>,
     * }
     */
    private function run(bool $dryRun): array
    {
        if ($this->alreadyApplied()) {
            return [
                'already_applied' => true,
                'orders' => [],
                'events' => [],
                'assignments' => [],
                'attempts' => [],
            ];
        }

        $result = [
            'already_applied' => false,
            'orders' => [],
            'events' => [],
            'assignments' => [],
            'attempts' => [],
        ];

        $apply = function () use (&$result): void {
            foreach (DB::table('gh_orders')->orderBy('id')->get() as $order) {
                $tz = trim((string) $order->timezone) !== '' ? (string) $order->timezone : 'Asia/Dubai';
                $changes = [];
                foreach (['pickup_starts_at', 'pickup_ends_at'] as $col) {
                    if ($order->{$col} === null) {
                        continue;
                    }
                    $new = $this->wallToUtc((string) $order->{$col}, $tz);
                    if ($new !== (string) $order->{$col}) {
                        $changes[$col] = ['before' => $order->{$col}, 'after' => $new, 'from_tz' => $tz];
                    }
                }
                foreach (['picked_up_at', 'delivered_at', 'cancelled_at', 'invoice_issued_at'] as $col) {
                    if ($order->{$col} === null) {
                        continue;
                    }
                    $new = $this->wallToUtc((string) $order->{$col}, $tz);
                    if ($new !== (string) $order->{$col}) {
                        $changes[$col] = ['before' => $order->{$col}, 'after' => $new, 'from_tz' => $tz];
                    }
                }
                if ($changes !== []) {
                    $result['orders'][] = ['id' => $order->id, 'reference' => $order->reference, 'changes' => $changes];
                    DB::table('gh_orders')->where('id', $order->id)->update(
                        collect($changes)->mapWithKeys(fn ($c, $col) => [$col => $c['after']])->all()
                    );
                }
            }

            foreach (DB::table('gh_order_events')->orderBy('id')->get() as $event) {
                $orderTz = (string) (DB::table('gh_orders')->where('id', $event->order_id)->value('timezone') ?: 'Asia/Dubai');
                $occTz = in_array((string) $event->event_type, self::ORDER_TZ_EVENT_TYPES, true)
                    ? $orderTz
                    : 'Africa/Cairo';
                $changes = [];
                if ($event->occurred_at !== null) {
                    $new = $this->wallToUtc((string) $event->occurred_at, $occTz);
                    if ($new !== (string) $event->occurred_at) {
                        $changes['occurred_at'] = ['before' => $event->occurred_at, 'after' => $new, 'from_tz' => $occTz];
                    }
                }
                if ($event->recorded_at !== null) {
                    $new = $this->wallToUtc((string) $event->recorded_at, 'Africa/Cairo');
                    if ($new !== (string) $event->recorded_at) {
                        $changes['recorded_at'] = ['before' => $event->recorded_at, 'after' => $new, 'from_tz' => 'Africa/Cairo'];
                    }
                }
                if ($changes !== []) {
                    $result['events'][] = ['id' => $event->id, 'order_id' => $event->order_id, 'event_type' => $event->event_type, 'changes' => $changes];
                    DB::table('gh_order_events')->where('id', $event->id)->update(
                        collect($changes)->mapWithKeys(fn ($c, $col) => [$col => $c['after']])->all()
                    );
                }
            }

            if (Schema::hasTable('gh_order_assignments')) {
                foreach (DB::table('gh_order_assignments')->orderBy('id')->get() as $row) {
                    $changes = [];
                    foreach (['assigned_at', 'unassigned_at'] as $col) {
                        if ($row->{$col} === null) {
                            continue;
                        }
                        $new = $this->wallToUtc((string) $row->{$col}, 'Africa/Cairo');
                        if ($new !== (string) $row->{$col}) {
                            $changes[$col] = ['before' => $row->{$col}, 'after' => $new, 'from_tz' => 'Africa/Cairo'];
                        }
                    }
                    if ($changes !== []) {
                        $result['assignments'][] = ['id' => $row->id, 'order_id' => $row->order_id, 'changes' => $changes];
                        DB::table('gh_order_assignments')->where('id', $row->id)->update(
                            collect($changes)->mapWithKeys(fn ($c, $col) => [$col => $c['after']])->all()
                        );
                    }
                }
            }

            if (Schema::hasTable('gh_order_attempts')) {
                foreach (DB::table('gh_order_attempts')->orderBy('id')->get() as $row) {
                    $orderTz = (string) (DB::table('gh_orders')->where('id', $row->order_id)->value('timezone') ?: 'Asia/Dubai');
                    $changes = [];
                    foreach (['occurred_at' => $orderTz, 'recorded_at' => 'Africa/Cairo'] as $col => $tz) {
                        if ($row->{$col} === null) {
                            continue;
                        }
                        $new = $this->wallToUtc((string) $row->{$col}, $tz);
                        if ($new !== (string) $row->{$col}) {
                            $changes[$col] = ['before' => $row->{$col}, 'after' => $new, 'from_tz' => $tz];
                        }
                    }
                    if ($changes !== []) {
                        $result['attempts'][] = ['id' => $row->id, 'order_id' => $row->order_id, 'changes' => $changes];
                        DB::table('gh_order_attempts')->where('id', $row->id)->update(
                            collect($changes)->mapWithKeys(fn ($c, $col) => [$col => $c['after']])->all()
                        );
                    }
                }
            }

            $now = Carbon::now('UTC')->format('Y-m-d H:i:s');
            DB::table('gh_sequences')->insert([
                'name' => self::MARKER,
                'next_value' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $apply();
            } finally {
                DB::rollBack();
            }

            return $result;
        }

        DB::transaction($apply);

        return $result;
    }

    private function wallToUtc(string $wall, string $fromTz): string
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $wall, $fromTz)
            ->utc()
            ->format('Y-m-d H:i:s');
    }
}
