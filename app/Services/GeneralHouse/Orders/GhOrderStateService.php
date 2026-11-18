<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAttempt;
use App\Models\User;
use App\Support\GeneralHouse\GhAttemptType;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Sole authority for gh_orders.status mutations (GH-4).
 * Controllers/jobs must call these methods — never update status directly.
 */
class GhOrderStateService
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        GhOrderStatus::LATER => [GhOrderStatus::PICKUP, GhOrderStatus::CANCELLED],
        GhOrderStatus::PICKUP => [GhOrderStatus::WORKING, GhOrderStatus::LATER, GhOrderStatus::CANCELLED],
        GhOrderStatus::WORKING => [GhOrderStatus::DELIVERY, GhOrderStatus::PICKUP, GhOrderStatus::CANCELLED],
        GhOrderStatus::DELIVERY => [GhOrderStatus::COMPLETED, GhOrderStatus::WORKING, GhOrderStatus::CANCELLED],
        GhOrderStatus::COMPLETED => [GhOrderStatus::DELIVERY],
        GhOrderStatus::CANCELLED => [GhOrderStatus::LATER, GhOrderStatus::PICKUP, GhOrderStatus::WORKING],
    ];

    public function __construct(
        private readonly GhOrderEventService $events,
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhDeliveryEligibilityService $deliveryEligibility,
        private readonly GhReceivedItemService $receivedItems,
        private readonly GhOrderAssignmentService $assignments,
    ) {}

    /**
     * Lazy resolve avoids circular DI with GhOrderManualTransitionService.
     */
    private function manualTransitions(): GhOrderManualTransitionService
    {
        return app(GhOrderManualTransitionService::class);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    /**
     * Generic entry is closed — callers must use named transitions.
     */
    public function transition(GhOrder $order, string $toStatus, User $actor, string $reason): GhOrder
    {
        throw ValidationException::withMessages([
            'status' => [__('gh.errors.order_use_named_transition', ['reason' => $reason])],
        ]);
    }

    /**
     * Scheduler / create-path helper: promote LATER → PICKUP when eligible.
     * Idempotent: already-PICKUP (or other) rows are skipped.
     *
     * @return int Number of orders promoted
     */
    public function promoteEligibleLaterOrders(?Carbon $now = null, int $chunk = 100): int
    {
        $now ??= $this->eligibility->now();
        // Bind UTC string so app.timezone cannot skew TIMESTAMP comparisons.
        $sqlCutoff = $this->eligibility->eligibleCutoffUtcSql($now);
        $promoted = 0;

        GhOrder::query()
            ->where('status', GhOrderStatus::LATER)
            ->where('pickup_starts_at', '<', $sqlCutoff)
            ->orderBy('id')
            ->chunkById($chunk, function ($orders) use ($now, &$promoted) {
                foreach ($orders as $order) {
                    if ($this->promoteOneToPickup($order, $now)) {
                        $promoted++;
                    }
                }
            });

        return $promoted;
    }

    public function promoteOneToPickup(GhOrder $order, ?Carbon $now = null): bool
    {
        $now ??= $this->eligibility->now();

        return (bool) DB::transaction(function () use ($order, $now) {
            /** @var GhOrder|null $locked */
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->first();

            // Null when an admin soft-deleted the order after the scheduler loaded its chunk.
            if ($locked === null || $locked->status !== GhOrderStatus::LATER) {
                return false;
            }
            if ($this->manualTransitions()->isAutoPromoteSuppressed($locked, GhOrderStatus::PICKUP, $now)) {
                return false;
            }
            if (! $this->eligibility->isEligibleForPickup($locked->pickup_starts_at, $now)) {
                return false;
            }

            $this->applyStatus($locked, GhOrderStatus::PICKUP, null, GhOrderEventType::PICKUP_PROMOTED, [
                'source' => 'scheduler',
                'trigger' => 'automatic',
                'rule' => 'pickup_calendar_day',
                'pickup_date' => $locked->pickup_starts_at->copy()->timezone($this->eligibility->timezone())->toDateString(),
            ]);

            return true;
        });
    }

    /**
     * PICKUP → WORKING (package pickup). Garment counting is staff work during WORKING.
     *
     * @param  array{
     *   received?: list<array{order_line_id?: int|null, offering_id: int, quantity: int, notes?: ?string}>,
     *   occurred_at?: string|null,
     *   notes?: string|null,
     *   evidence_note?: string|null
     * }  $data
     */
    public function confirmPickup(GhOrder $order, User $actor, array $data): GhOrder
    {
        if (! GhOrderAccess::mayConfirmPickup($actor, $order)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.pickup_confirm_forbidden')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $data) {
            /** @var GhOrder $locked */
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Idempotent: already confirmed — do not touch existing received lines.
            if ($locked->status === GhOrderStatus::WORKING && $locked->picked_up_at !== null) {
                return $locked->fresh(['lines', 'receivedLines', 'events']);
            }

            $this->assertCanTransition($locked->status, GhOrderStatus::WORKING);

            $received = $data['received'] ?? [];
            // Optional legacy received payload still accepted; empty = package only.
            if (is_array($received) && $received !== []) {
                $snapshots = $this->receivedItems->buildReceivedSnapshots($locked, $received);
                $this->receivedItems->persist($locked, $snapshots);
            }

            $receivedCount = $locked->receivedLines()->count();

            // Re-open from PICKUP with preserved pickup evidence: never overwrite original stamp.
            if ($locked->picked_up_at !== null) {
                $locked->forceFill(['updated_by' => $actor->id])->save();
                $this->applyStatus($locked, GhOrderStatus::WORKING, $actor, GhOrderEventType::PICKUP_CONFIRMED, [
                    'package_pickup' => true,
                    'received_count' => $receivedCount,
                    'preserved_existing_pickup' => true,
                    'picked_up_at' => $locked->picked_up_at->toIso8601String(),
                    'pickup_confirmed_by' => $locked->pickup_confirmed_by,
                ], $locked->picked_up_at);

                $fresh = $locked->fresh(['lines', 'receivedLines', 'events']);
                $this->promoteOneToDelivery($fresh, $actor, 'pickup_reevaluation');

                return $fresh->fresh(['lines', 'receivedLines', 'events', 'pickupDriver', 'deliveryDriver']);
            }

            $occurredAt = $this->parseOccurredAt($data['occurred_at'] ?? null, $locked);
            $notes = $data['notes'] ?? $data['evidence_note'] ?? null;

            $locked->forceFill([
                'picked_up_at' => $occurredAt,
                'pickup_confirmed_by' => $actor->id,
                'pickup_confirmation_notes' => $notes,
                'updated_by' => $actor->id,
            ])->save();

            $this->applyStatus($locked, GhOrderStatus::WORKING, $actor, GhOrderEventType::PICKUP_CONFIRMED, [
                'package_pickup' => true,
                'received_count' => $receivedCount,
                'notes' => $notes,
                'occurred_at' => $occurredAt->toIso8601String(),
            ], $occurredAt);

            $fresh = $locked->fresh(['lines', 'receivedLines', 'events']);
            // Late pickup after the scheduled delivery window: promote immediately when eligible.
            // Do not auto-copy pickup driver onto delivery — manager assigns delivery driver separately.
            $this->promoteOneToDelivery($fresh, $actor, 'pickup_reevaluation');

            return $fresh->fresh(['lines', 'receivedLines', 'events', 'pickupDriver', 'deliveryDriver']);
        });
    }

    /**
     * Record invoice issuance on the order. Does NOT require or force DELIVERY.
     * If timing rules already allow DELIVERY, promotes as a side effect of reevaluation.
     *
     * @param  array{invoice_id: int|string, invoice_number?: string, issued_at?: string|Carbon}  $invoiceProof
     */
    public function markDeliveryAfterInvoice(GhOrder $order, User $actor, array $invoiceProof): GhOrder
    {
        $invoiceId = $invoiceProof['invoice_id'] ?? null;
        if ($invoiceId === null || $invoiceId === '' || $invoiceId === 0 || $invoiceId === '0') {
            throw ValidationException::withMessages([
                'invoice' => [__('gh.errors.invoice_required_for_delivery')],
            ]);
        }

        if (! $this->invoiceArtifactExists($invoiceId)) {
            throw ValidationException::withMessages([
                'invoice' => [__('gh.errors.invoice_not_issued')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $invoiceProof, $invoiceId) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice_issued_at !== null) {
                return $locked->fresh();
            }

            if (isset($invoiceProof['issued_at']) && $invoiceProof['issued_at'] instanceof Carbon) {
                $issuedAt = $invoiceProof['issued_at']->copy()->utc();
            } elseif (isset($invoiceProof['issued_at']) && is_string($invoiceProof['issued_at']) && $invoiceProof['issued_at'] !== '') {
                $issuedAt = Carbon::parse($invoiceProof['issued_at'], 'UTC');
            } else {
                $issuedAt = Carbon::now('UTC');
            }

            $locked->forceFill([
                'invoice_issued_at' => $issuedAt,
                'updated_by' => $actor->id,
            ])->save();

            $this->events->record(
                $locked,
                GhOrderEventType::INVOICE_ISSUED,
                $actor,
                $locked->status,
                $locked->status,
                [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceProof['invoice_number'] ?? null,
                    'source' => 'invoice_issuance',
                ],
                $issuedAt,
            );

            // Invoice is independent of logistics; only promote when timing rules already permit.
            $this->promoteOneToDelivery($locked->fresh(), $actor, 'invoice_reevaluation');

            return $locked->fresh();
        });
    }

    /**
     * Scheduler: promote eligible WORKING orders to DELIVERY (invoice not required).
     *
     * @return int Number of orders promoted
     */
    public function promoteEligibleWorkingToDelivery(?Carbon $now = null, int $chunk = 100): int
    {
        $now ??= $this->deliveryEligibility->now();
        $promoted = 0;

        GhOrder::query()
            ->where('status', GhOrderStatus::WORKING)
            ->whereNotNull('picked_up_at')
            ->orderBy('id')
            ->chunkById($chunk, function ($orders) use ($now, &$promoted) {
                foreach ($orders as $order) {
                    if ($this->promoteOneToDelivery($order, null, 'scheduler', $now)) {
                        $promoted++;
                    }
                }
            });

        return $promoted;
    }

    /**
     * WORKING → DELIVERY when pickup confirmed and timing rules pass. Idempotent.
     *
     * @param  'scheduler'|'manual'|'pickup_reevaluation'|'invoice_reevaluation'|'schedule_reevaluation'  $source
     */
    public function promoteOneToDelivery(
        GhOrder $order,
        ?User $actor = null,
        string $source = 'scheduler',
        ?Carbon $now = null,
    ): bool {
        $now ??= $this->deliveryEligibility->now();

        return (bool) DB::transaction(function () use ($order, $actor, $source, $now) {
            /** @var GhOrder $locked */
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GhOrderStatus::DELIVERY) {
                return false;
            }
            if (in_array($locked->status, [GhOrderStatus::COMPLETED, GhOrderStatus::CANCELLED], true)) {
                return false;
            }
            if ($locked->status !== GhOrderStatus::WORKING || $locked->picked_up_at === null) {
                return false;
            }

            $isManual = $source === 'manual';
            if ($isManual) {
                if (! $this->deliveryEligibility->isEligibleForManualDelivery($locked)) {
                    return false;
                }
            } else {
                if ($this->manualTransitions()->isAutoPromoteSuppressed($locked, GhOrderStatus::DELIVERY, $now)) {
                    return false;
                }
                if (! $this->deliveryEligibility->isEligibleForDelivery($locked, $now)) {
                    return false;
                }
            }

            $this->assertCanTransition($locked->status, GhOrderStatus::DELIVERY);

            $locked = $locked->fresh();
            // Explicit manager advance clears indefinite hold (same as manual WORKING→DELIVERY).
            if ($isManual && $locked->auto_promote_held) {
                $locked->forceFill([
                    'auto_promote_held' => false,
                    'auto_promote_held_at' => null,
                    'auto_promote_held_by' => null,
                    'auto_promote_held_reason' => null,
                    'updated_by' => $actor?->id,
                ])->save();
            }

            $this->applyStatus($locked, GhOrderStatus::DELIVERY, $actor, GhOrderEventType::DELIVERY_READY, [
                'source' => $source,
                'trigger' => $isManual ? 'manual' : 'automatic',
                'eligible_at' => $this->deliveryEligibility->eligibleAt($locked)?->toIso8601String(),
                'delivery_driver_id' => $locked->delivery_driver_id,
                'has_invoice' => $locked->invoice_issued_at !== null,
                'has_scheduled_delivery' => $locked->scheduled_delivery_at !== null,
            ], $now);

            return true;
        });
    }

    /**
     * Manager-triggered WORKING → DELIVERY after confirmed pickup (schedule optional).
     */
    public function markDeliveryReady(GhOrder $order, User $actor): GhOrder
    {
        if (! GhOrderAccess::mayManage($actor)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.delivery_ready_forbidden')],
            ]);
        }

        $ok = $this->promoteOneToDelivery($order, $actor, 'manual');
        if (! $ok && $order->fresh()->status !== GhOrderStatus::DELIVERY) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.delivery_not_eligible_yet')],
            ]);
        }

        return $order->fresh();
    }

    /**
     * Previously copied pickup → delivery when unassigned. Owner rule: delivery driver stays
     * optional and must never be silently filled. Kept as a no-op for call-site compatibility.
     */
    public function ensureDefaultDeliveryDriver(GhOrder $order, ?User $actor = null): GhOrder
    {
        return $order;
    }

    /**
     * DELIVERY → COMPLETED.
     *
     * @param  array{occurred_at?: string|null, notes?: string|null}  $data
     */
    public function confirmDelivery(GhOrder $order, User $actor, array $data = []): GhOrder
    {
        if (! GhOrderAccess::mayConfirmDelivery($actor, $order)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.delivery_confirm_forbidden')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $data) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GhOrderStatus::COMPLETED && $locked->delivered_at !== null) {
                return $locked->fresh();
            }

            if ($locked->status === GhOrderStatus::WORKING) {
                throw ValidationException::withMessages([
                    'status' => [__('gh.errors.delivery_requires_delivery_state')],
                ]);
            }

            // Reopened COMPLETED→DELIVERY keeps original delivery evidence — recomplete without rewrite.
            if ($locked->delivered_at !== null) {
                $this->assertCanTransition($locked->status, GhOrderStatus::COMPLETED);
                $locked->forceFill(['updated_by' => $actor->id])->save();
                $this->applyStatus($locked, GhOrderStatus::COMPLETED, $actor, GhOrderEventType::STATUS_CHANGED, [
                    'source' => 'recomplete_preserved_delivery',
                    'trigger' => 'manual',
                    'preserved_delivery' => true,
                    'delivered_at' => $locked->delivered_at->toIso8601String(),
                    'delivered_by' => $locked->delivered_by,
                    'no_new_collection' => true,
                ], $locked->delivered_at);

                return $locked->fresh();
            }

            $this->assertCanTransition($locked->status, GhOrderStatus::COMPLETED);

            $occurredAt = $this->parseOccurredAt($data['occurred_at'] ?? null, $locked);
            $notes = $data['notes'] ?? null;

            $locked->forceFill([
                'delivered_at' => $occurredAt,
                'delivered_by' => $actor->id,
                'delivery_notes' => $notes,
                'updated_by' => $actor->id,
            ])->save();

            $this->applyStatus($locked, GhOrderStatus::COMPLETED, $actor, GhOrderEventType::DELIVERY_CONFIRMED, [
                'notes' => $notes,
                'occurred_at' => $occurredAt->toIso8601String(),
            ], $occurredAt);

            return $locked->fresh();
        });
    }

    /**
     * Cancel before invoice issuance. Post-invoice blocked pending financial rules.
     */
    public function cancel(GhOrder $order, User $actor, string $reason): GhOrder
    {
        if (! GhOrderAccess::mayManage($actor)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.cancel_forbidden')],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancel_reason' => [__('gh.errors.cancel_reason_required')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GhOrderStatus::CANCELLED) {
                return $locked->fresh();
            }

            if ($locked->invoice_issued_at !== null || $locked->status === GhOrderStatus::DELIVERY
                || $locked->status === GhOrderStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'order' => [__('gh.errors.cancel_after_invoice_blocked')],
                ]);
            }

            $this->assertCanTransition($locked->status, GhOrderStatus::CANCELLED);

            $now = Carbon::now();
            $locked->forceFill([
                'cancelled_at' => $now,
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            $this->applyStatus($locked, GhOrderStatus::CANCELLED, $actor, GhOrderEventType::ORDER_CANCELLED, [
                'reason' => $reason,
            ], $now);

            return $locked->fresh();
        });
    }

    /**
     * Reschedule pickup window; recompute LATER/PICKUP eligibility. Pre-pickup only.
     *
     * @param  array{starts_at: Carbon, ends_at: Carbon, timezone: string, scheduling_notes?: ?string}  $window
     */
    public function reschedulePickup(GhOrder $order, User $actor, array $window): GhOrder
    {
        if (! GhOrderAccess::mayManage($actor)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.reschedule_forbidden')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $window) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->picked_up_at !== null || ! GhOrderStatus::isPrePickup($locked->status)) {
                throw ValidationException::withMessages([
                    'order' => [__('gh.errors.reschedule_after_pickup')],
                ]);
            }
            if ($locked->invoice_issued_at !== null) {
                throw ValidationException::withMessages([
                    'order' => [__('gh.errors.order_locked_invoiced')],
                ]);
            }

            $newStatus = $this->eligibility->initialStatus($window['starts_at']);
            $from = $locked->status;

            $locked->forceFill([
                'pickup_starts_at' => $window['starts_at'],
                'pickup_ends_at' => $window['ends_at'],
                'timezone' => $window['timezone'],
                'scheduling_notes' => array_key_exists('scheduling_notes', $window)
                    ? $window['scheduling_notes']
                    : $locked->scheduling_notes,
                'updated_by' => $actor->id,
            ])->save();

            $this->events->record(
                $locked,
                GhOrderEventType::PICKUP_RESCHEDULED,
                $actor,
                null,
                null,
                [
                    'pickup_starts_at' => $window['starts_at']->toIso8601String(),
                    'pickup_ends_at' => $window['ends_at']->toIso8601String(),
                    'timezone' => $window['timezone'],
                ],
            );

            if ($from !== $newStatus) {
                $this->assertCanTransition($from, $newStatus);
                $this->applyStatus($locked, $newStatus, $actor, GhOrderEventType::STATUS_CHANGED, [
                    'source' => 'reschedule_eligibility',
                ]);
            }

            return $locked->fresh();
        });
    }

    /**
     * @param  array{occurred_at?: string|null, notes?: string|null, reason_code?: string|null}  $data
     */
    public function recordFailedPickup(GhOrder $order, User $actor, array $data = []): GhOrderAttempt
    {
        if (! GhOrderAccess::mayRecordPickupAttempt($actor, $order)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.pickup_attempt_forbidden')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $data) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== GhOrderStatus::PICKUP) {
                throw ValidationException::withMessages([
                    'status' => [__('gh.errors.failed_pickup_status')],
                ]);
            }

            $occurredAt = $this->parseOccurredAt($data['occurred_at'] ?? null, $locked);
            $notes = $data['notes'] ?? null;
            $reason = isset($data['reason_code']) ? (string) $data['reason_code'] : null;

            $attempt = GhOrderAttempt::query()->create([
                'order_id' => $locked->id,
                'attempt_type' => GhAttemptType::PICKUP,
                'outcome' => 'FAILED',
                'actor_id' => $actor->id,
                'occurred_at' => $occurredAt,
                'recorded_at' => Carbon::now('UTC'),
                'notes' => $notes,
                'payload' => $reason !== null && $reason !== '' ? ['reason_code' => $reason] : null,
            ]);

            $this->events->record(
                $locked,
                GhOrderEventType::PICKUP_FAILED,
                $actor,
                $locked->status,
                $locked->status,
                ['attempt_id' => $attempt->id, 'notes' => $notes, 'reason_code' => $reason],
                $occurredAt,
            );

            return $attempt;
        });
    }

    /**
     * @param  array{occurred_at?: string|null, notes?: string|null, reason_code?: string|null}  $data
     */
    public function recordFailedDelivery(GhOrder $order, User $actor, array $data = []): GhOrderAttempt
    {
        if (! GhOrderAccess::mayRecordDeliveryAttempt($actor, $order)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.delivery_attempt_forbidden')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $data) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== GhOrderStatus::DELIVERY) {
                throw ValidationException::withMessages([
                    'status' => [__('gh.errors.failed_delivery_status')],
                ]);
            }

            $occurredAt = $this->parseOccurredAt($data['occurred_at'] ?? null, $locked);
            $notes = $data['notes'] ?? null;
            $reason = isset($data['reason_code']) ? (string) $data['reason_code'] : null;

            $attempt = GhOrderAttempt::query()->create([
                'order_id' => $locked->id,
                'attempt_type' => GhAttemptType::DELIVERY,
                'outcome' => 'FAILED',
                'actor_id' => $actor->id,
                'occurred_at' => $occurredAt,
                'recorded_at' => Carbon::now('UTC'),
                'notes' => $notes,
                'payload' => $reason !== null && $reason !== '' ? ['reason_code' => $reason] : null,
            ]);

            $this->events->record(
                $locked,
                GhOrderEventType::DELIVERY_FAILED,
                $actor,
                $locked->status,
                $locked->status,
                ['attempt_id' => $attempt->id, 'notes' => $notes, 'reason_code' => $reason],
                $occurredAt,
            );

            return $attempt;
        });
    }

    /**
     * Re-evaluate LATER/PICKUP after operational edit (pre-pickup). Status via this service only.
     */
    public function reevaluatePrePickupStatus(GhOrder $order, User $actor): GhOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! GhOrderStatus::isPrePickup($locked->status) || $locked->picked_up_at !== null) {
                return $locked;
            }

            $newStatus = $this->eligibility->initialStatus($locked->pickup_starts_at);
            if ($newStatus === $locked->status) {
                return $locked;
            }

            $this->assertCanTransition($locked->status, $newStatus);
            $this->applyStatus($locked, $newStatus, $actor, GhOrderEventType::STATUS_CHANGED, [
                'source' => 'edit_reevaluation',
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Public writer for GhOrderManualTransitionService only — still asserts ALLOWED graph.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyManualStatus(
        GhOrder $order,
        string $toStatus,
        User $actor,
        string $eventType,
        array $payload = [],
        ?Carbon $occurredAt = null,
    ): void {
        $this->assertCanTransition($order->status, $toStatus);
        $this->applyStatus($order, $toStatus, $actor, $eventType, $payload, $occurredAt);
    }

    private function applyStatus(
        GhOrder $order,
        string $toStatus,
        ?User $actor,
        string $eventType,
        array $payload = [],
        ?Carbon $occurredAt = null,
    ): void {
        $from = $order->status;
        if ($from === $toStatus) {
            return;
        }

        $order->forceFill(['status' => $toStatus])->save();
        $this->events->record(
            $order,
            $eventType,
            $actor,
            $from,
            $toStatus,
            $payload,
            $occurredAt,
        );
    }

    private function assertCanTransition(string $from, string $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.order_transition_denied')],
            ]);
        }
    }

    private function parseOccurredAt(mixed $raw, GhOrder $order): Carbon
    {
        $tz = $this->eligibility->displayTimezone($order->timezone);

        try {
            $at = ($raw === null || $raw === '')
                ? Carbon::now($tz)
                : Carbon::parse((string) $raw, $tz);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'occurred_at' => [__('gh.errors.occurred_at_invalid')],
            ]);
        }

        $atUtc = $at->copy()->utc();
        // Reject fabricated future operational stamps (allow small clock skew).
        if ($atUtc->greaterThan(Carbon::now('UTC')->addMinutes(15))) {
            throw ValidationException::withMessages([
                'occurred_at' => [__('gh.errors.occurred_at_future')],
            ]);
        }

        return $atUtc;
    }

    private function invoiceArtifactExists(int|string $invoiceId): bool
    {
        // GH-5 creates gh_invoices; until then WORKING→DELIVERY is impossible.
        if (! Schema::hasTable('gh_invoices')) {
            return false;
        }

        return DB::table('gh_invoices')
            ->where('id', $invoiceId)
            ->where('status', 'ISSUED')
            ->exists();
    }
}
