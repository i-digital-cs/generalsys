<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Management-only forward/backward operational status corrections.
 * Does not mutate invoices, payments, refunds, or tax rows.
 */
final class GhOrderManualTransitionService
{
    /**
     * Safe manual pairs (from → list of to), including conditional reopen/restore.
     *
     * @var array<string, list<string>>
     */
    public const MANUAL_MATRIX = [
        GhOrderStatus::LATER => [GhOrderStatus::PICKUP],
        GhOrderStatus::PICKUP => [GhOrderStatus::LATER, GhOrderStatus::WORKING],
        GhOrderStatus::WORKING => [GhOrderStatus::PICKUP, GhOrderStatus::DELIVERY],
        GhOrderStatus::DELIVERY => [GhOrderStatus::WORKING],
        GhOrderStatus::COMPLETED => [GhOrderStatus::DELIVERY],
        GhOrderStatus::CANCELLED => [GhOrderStatus::LATER, GhOrderStatus::PICKUP, GhOrderStatus::WORKING],
    ];

    public function __construct(
        private readonly GhOrderStateService $states,
        private readonly GhOrderEventService $events,
        private readonly GhPickupEligibilityService $eligibility,
    ) {}

    /**
     * @return list<string>
     */
    public function permittedDestinations(GhOrder $order): array
    {
        $candidates = self::MANUAL_MATRIX[$order->status] ?? [];
        $out = [];
        foreach ($candidates as $to) {
            if ($this->explainBlocker($order, $to) === null) {
                $out[] = $to;
            }
        }

        return $out;
    }

    /**
     * Human-readable blocker or null when allowed (ignores actor auth).
     */
    public function explainBlocker(GhOrder $order, string $toStatus): ?string
    {
        if ($order->status === $toStatus) {
            return __('gh.errors.manual_status_same');
        }

        $allowed = self::MANUAL_MATRIX[$order->status] ?? [];
        if (! in_array($toStatus, $allowed, true)) {
            return __('gh.errors.manual_status_not_allowed');
        }

        if ($order->status === GhOrderStatus::PICKUP && $toStatus === GhOrderStatus::WORKING) {
            if ($order->picked_up_at === null) {
                return __('gh.errors.manual_status_needs_pickup_confirm');
            }
        }

        if ($order->status === GhOrderStatus::LATER && $toStatus === GhOrderStatus::PICKUP) {
            if ($order->pickup_driver_id === null) {
                return __('gh.errors.manual_status_pickup_driver_required');
            }
            // Early force allowed when ineligible — reason enforced in transition().
        }

        if ($order->status === GhOrderStatus::WORKING && $toStatus === GhOrderStatus::DELIVERY) {
            if ($order->picked_up_at === null) {
                return __('gh.errors.manual_status_needs_pickup_confirm');
            }
        }

        if ($order->status === GhOrderStatus::COMPLETED && $toStatus === GhOrderStatus::DELIVERY) {
            if ($order->delivered_at === null) {
                return __('gh.errors.manual_status_completed_missing_delivery');
            }
        }

        if ($order->status === GhOrderStatus::CANCELLED) {
            $cancelBlock = $this->explainCancelRestoreBlocker($order, $toStatus);
            if ($cancelBlock !== null) {
                return $cancelBlock;
            }
        }

        return null;
    }

    public function isBackward(string $from, string $to): bool
    {
        $rank = [
            GhOrderStatus::LATER => 1,
            GhOrderStatus::PICKUP => 2,
            GhOrderStatus::WORKING => 3,
            GhOrderStatus::DELIVERY => 4,
            GhOrderStatus::COMPLETED => 5,
            GhOrderStatus::CANCELLED => 0,
        ];

        if ($from === GhOrderStatus::CANCELLED) {
            return false; // restore is a special forward reopen
        }
        if ($from === GhOrderStatus::COMPLETED && $to === GhOrderStatus::DELIVERY) {
            return true;
        }

        return ($rank[$to] ?? 0) < ($rank[$from] ?? 0);
    }

    public function requiresReason(GhOrder $order, string $toStatus): bool
    {
        if ($this->isBackward($order->status, $toStatus)) {
            return true;
        }
        if ($order->status === GhOrderStatus::CANCELLED) {
            return true;
        }
        if ($order->status === GhOrderStatus::LATER && $toStatus === GhOrderStatus::PICKUP
            && ! $this->eligibility->isEligibleForPickup($order->pickup_starts_at)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{to_status: string, reason?: string|null}  $data
     */
    public function transition(GhOrder $order, User $actor, array $data): GhOrder
    {
        if (! GhOrderAccess::mayManage($actor)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.manual_status_forbidden')],
            ]);
        }

        $toStatus = (string) ($data['to_status'] ?? '');
        $reason = trim((string) ($data['reason'] ?? ''));

        if (! in_array($toStatus, GhOrderStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'to_status' => [__('gh.errors.order_status_invalid')],
            ]);
        }

        if ($order->status === $toStatus) {
            return $order->fresh(['events', 'pickupDriver', 'deliveryDriver']);
        }

        $blocker = $this->explainBlocker($order, $toStatus);
        if ($blocker !== null) {
            throw ValidationException::withMessages([
                'to_status' => [$blocker],
            ]);
        }

        $needsReason = $this->requiresReason($order, $toStatus);
        if ($needsReason && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => [__('gh.errors.manual_status_reason_required')],
            ]);
        }
        if ($reason === '') {
            $reason = __('gh.orders.manual_status_forward_reason_default');
        }
        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => [__('gh.errors.manual_status_reason_too_long')],
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $toStatus, $reason) {
            /** @var GhOrder $locked */
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $toStatus) {
                return $locked->fresh(['events', 'pickupDriver', 'deliveryDriver']);
            }

            $blocker = $this->explainBlocker($locked, $toStatus);
            if ($blocker !== null) {
                throw ValidationException::withMessages([
                    'to_status' => [$blocker],
                ]);
            }

            $from = $locked->status;
            $backward = $this->isBackward($from, $toStatus);
            $earlyForce = $from === GhOrderStatus::LATER
                && $toStatus === GhOrderStatus::PICKUP
                && ! $this->eligibility->isEligibleForPickup($locked->pickup_starts_at);

            $payload = [
                'source' => 'MANUAL',
                'trigger' => 'manual',
                'reason' => $reason,
                'direction' => $backward ? 'backward' : 'forward',
                'actor_id' => $actor->id,
            ];

            if ($earlyForce) {
                $payload['early_force'] = true;
                $payload['pickup_driver_id'] = $locked->pickup_driver_id;
            }

            // Preserve pickup evidence forever on the order row + audit snapshot.
            if ($from === GhOrderStatus::WORKING && $toStatus === GhOrderStatus::PICKUP) {
                $payload['preserved_pickup'] = [
                    'picked_up_at' => $locked->picked_up_at?->toIso8601String(),
                    'pickup_confirmed_by' => $locked->pickup_confirmed_by,
                    'pickup_confirmation_notes' => $locked->pickup_confirmation_notes,
                    'cleared' => false,
                ];
            }

            if ($from === GhOrderStatus::COMPLETED && $toStatus === GhOrderStatus::DELIVERY) {
                $payload['preserved_delivery'] = [
                    'delivered_at' => $locked->delivered_at?->toIso8601String(),
                    'delivered_by' => $locked->delivered_by,
                    'delivery_notes' => $locked->delivery_notes,
                    'cleared' => false,
                ];
                $payload['financial_untouched'] = true;
            }

            if ($from === GhOrderStatus::CANCELLED) {
                $payload['restored_from_cancel'] = [
                    'cancelled_at' => $locked->cancelled_at?->toIso8601String(),
                    'cancelled_by' => $locked->cancelled_by,
                    'cancel_reason' => $locked->cancel_reason,
                ];
                $locked->forceFill([
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancel_reason' => null,
                ]);
            }

            // Demotions that auto-promote could undo → indefinite hold until Resume.
            $holdAfter = ($from === GhOrderStatus::PICKUP && $toStatus === GhOrderStatus::LATER)
                || ($from === GhOrderStatus::DELIVERY && $toStatus === GhOrderStatus::WORKING)
                || ($from === GhOrderStatus::COMPLETED && $toStatus === GhOrderStatus::DELIVERY);

            if ($holdAfter) {
                $locked->forceFill([
                    'auto_promote_held' => true,
                    'auto_promote_held_at' => $this->eligibility->now()->utc(),
                    'auto_promote_held_by' => $actor->id,
                    'auto_promote_held_reason' => $reason,
                ]);
                $payload['auto_promote_held'] = true;
            }

            // Manual forward clears hold (manager chose to advance).
            $clearHold = ($from === GhOrderStatus::LATER && $toStatus === GhOrderStatus::PICKUP)
                || ($from === GhOrderStatus::WORKING && $toStatus === GhOrderStatus::DELIVERY)
                || ($from === GhOrderStatus::CANCELLED);

            if ($clearHold && ! $holdAfter) {
                $locked->forceFill([
                    'auto_promote_held' => false,
                    'auto_promote_held_at' => null,
                    'auto_promote_held_by' => null,
                    'auto_promote_held_reason' => null,
                ]);
            }

            $locked->forceFill(['updated_by' => $actor->id])->save();

            $this->states->applyManualStatus(
                $locked,
                $toStatus,
                $actor,
                GhOrderEventType::MANUAL_STATUS_TRANSITION,
                $payload,
            );

            // Delivery driver is never auto-filled from pickup on manual WORKING→DELIVERY.
            return $locked->fresh(['events', 'pickupDriver', 'deliveryDriver']);
        });
    }

    /**
     * Clear indefinite auto-promote hold so scheduler may act again.
     *
     * @param  array{reason?: string|null}  $data
     */
    public function resumeAutoPromote(GhOrder $order, User $actor, array $data = []): GhOrder
    {
        if (! GhOrderAccess::mayManage($actor)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.manual_status_forbidden')],
            ]);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            $reason = __('gh.orders.auto_promote_resume_reason_default');
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            /** @var GhOrder $locked */
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->auto_promote_held) {
                return $locked->fresh();
            }

            $locked->forceFill([
                'auto_promote_held' => false,
                'auto_promote_held_at' => null,
                'auto_promote_held_by' => null,
                'auto_promote_held_reason' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->events->record(
                $locked,
                GhOrderEventType::AUTO_PROMOTE_RESUMED,
                $actor,
                $locked->status,
                $locked->status,
                [
                    'source' => 'MANUAL',
                    'trigger' => 'manual',
                    'reason' => $reason,
                    'actor_id' => $actor->id,
                ],
            );

            return $locked->fresh(['events']);
        });
    }

    public function isAutoPromoteSuppressed(GhOrder $order, string $autoTarget, ?Carbon $now = null): bool
    {
        unset($autoTarget, $now);

        return (bool) $order->auto_promote_held;
    }

    /**
     * Preferred restore status after cancel when caller did not pick one.
     */
    public function suggestedCancelRestoreStatus(GhOrder $order): string
    {
        if ($order->picked_up_at !== null) {
            return GhOrderStatus::WORKING;
        }
        if ($this->eligibility->isEligibleForPickup($order->pickup_starts_at)) {
            return GhOrderStatus::PICKUP;
        }

        return GhOrderStatus::LATER;
    }

    private function explainCancelRestoreBlocker(GhOrder $order, string $toStatus): ?string
    {
        if ($order->invoice_issued_at !== null) {
            return __('gh.errors.manual_status_restore_has_invoice');
        }

        $invoice = $order->relationLoaded('invoice') ? $order->invoice : $order->invoice()->first();
        if ($invoice !== null) {
            if ($invoice->isIssued()) {
                return __('gh.errors.manual_status_restore_has_invoice');
            }
            $invoice->loadMissing(['payments', 'refunds', 'creditNotes', 'financialCorrections']);
            if ($invoice->payments->isNotEmpty()) {
                return __('gh.errors.manual_status_restore_has_payments');
            }
            // Any refund / credit-note / correction row blocks — settlement risk.
            if ($invoice->refunds->isNotEmpty()) {
                return __('gh.errors.manual_status_restore_has_refunds');
            }
            if ($invoice->creditNotes->isNotEmpty()) {
                return __('gh.errors.manual_status_restore_has_credit_notes');
            }
            if ($invoice->financialCorrections->isNotEmpty()) {
                return __('gh.errors.manual_status_restore_has_corrections');
            }
        }

        // Only restore to states compatible with evidence on the order.
        if ($toStatus === GhOrderStatus::WORKING && $order->picked_up_at === null) {
            return __('gh.errors.manual_status_restore_needs_pickup');
        }
        if ($toStatus === GhOrderStatus::PICKUP && $order->picked_up_at !== null) {
            // Prefer WORKING when pickup already confirmed.
            return __('gh.errors.manual_status_restore_use_working');
        }

        return null;
    }
}
