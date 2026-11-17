<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Read-only operational / financial warning badges for GH order surfaces.
 * Does not mutate orders, invoices, payments, or ledger rows.
 *
 * Audience:
 * - admin: missing invoice, completed unpaid/partial, overdue pickup/delivery
 * - driver: operational overdue only (no finance prompts they cannot act on)
 */
final class GhOrderWarningService
{
    public const AUDIENCE_ADMIN = 'admin';

    public const AUDIENCE_DRIVER = 'driver';

    public const CODE_MISSING_INVOICE = 'missing_invoice';

    public const CODE_COMPLETED_UNPAID = 'completed_unpaid';

    public const CODE_COMPLETED_PARTIAL = 'completed_partial';

    public const CODE_OVERDUE_PICKUP = 'overdue_pickup';

    public const CODE_OVERDUE_DELIVERY = 'overdue_delivery';

    public const CODE_NEEDS_DELIVERY_DRIVER = 'needs_delivery_driver';

    public function __construct(
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    /**
     * @return list<array{code: string, tone: 'orange'|'red', label: string}>
     */
    public function forOrder(GhOrder $order, string $audience = self::AUDIENCE_ADMIN, ?Carbon $now = null): array
    {
        $audience = $audience === self::AUDIENCE_DRIVER ? self::AUDIENCE_DRIVER : self::AUDIENCE_ADMIN;
        $now ??= $this->eligibility->now();
        $warnings = [];

        if ($order->status === GhOrderStatus::CANCELLED) {
            return [];
        }

        if ($audience === self::AUDIENCE_ADMIN && $this->needsDeliveryDriver($order)) {
            $warnings[] = $this->warning(
                self::CODE_NEEDS_DELIVERY_DRIVER,
                'orange',
                __('gh.warnings.needs_delivery_driver'),
            );
        }

        if ($audience === self::AUDIENCE_ADMIN && $this->isMissingIssuedInvoice($order)) {
            $warnings[] = $this->warning(
                self::CODE_MISSING_INVOICE,
                'orange',
                __('gh.warnings.missing_invoice'),
            );
        }

        if ($audience === self::AUDIENCE_ADMIN) {
            $pay = $this->completedPaymentWarning($order);
            if ($pay !== null) {
                $warnings[] = $pay;
            }
        }

        if ($this->isOverduePickup($order, $now)) {
            $warnings[] = $this->warning(
                self::CODE_OVERDUE_PICKUP,
                'red',
                __('gh.warnings.overdue_pickup'),
            );
        }

        if ($this->isOverdueDelivery($order, $now)) {
            $warnings[] = $this->warning(
                self::CODE_OVERDUE_DELIVERY,
                'red',
                __('gh.warnings.overdue_delivery'),
            );
        }

        return $warnings;
    }

    public function isMissingIssuedInvoice(GhOrder $order): bool
    {
        if ($order->invoice_issued_at !== null) {
            return false;
        }

        $invoice = $order->relationLoaded('invoice') ? $order->invoice : $order->invoice()->first();
        if ($invoice !== null && $invoice->status === GhInvoiceStatus::ISSUED) {
            return false;
        }

        return true;
    }

    public function isOverduePickup(GhOrder $order, ?Carbon $now = null): bool
    {
        $now ??= $this->eligibility->now();
        if ($order->status === GhOrderStatus::CANCELLED) {
            return false;
        }
        if ($order->picked_up_at !== null) {
            return false;
        }
        if ($order->pickup_starts_at === null) {
            return false;
        }
        if (! in_array($order->status, [GhOrderStatus::LATER, GhOrderStatus::PICKUP], true)) {
            return false;
        }

        return $order->pickup_starts_at->lt($now);
    }

    public function isOverdueDelivery(GhOrder $order, ?Carbon $now = null): bool
    {
        $now ??= $this->eligibility->now();
        if ($order->status === GhOrderStatus::CANCELLED) {
            return false;
        }
        if ($order->delivered_at !== null || $order->status === GhOrderStatus::COMPLETED) {
            return false;
        }
        if ($order->scheduled_delivery_at === null) {
            return false;
        }

        return $order->scheduled_delivery_at->lt($now);
    }

    public function needsDeliveryDriver(GhOrder $order): bool
    {
        return $order->status === GhOrderStatus::DELIVERY
            && $order->delivery_driver_id === null;
    }

    /**
     * @return array{code: string, tone: 'red', label: string}|null
     */
    private function completedPaymentWarning(GhOrder $order): ?array
    {
        if ($order->status !== GhOrderStatus::COMPLETED) {
            return null;
        }

        $invoice = $order->relationLoaded('invoice') ? $order->invoice : $order->invoice()->first();
        if ($invoice === null || $invoice->status !== GhInvoiceStatus::ISSUED) {
            // Unknown / unbilled — never invent a payable balance.
            return null;
        }

        $balance = $this->balances->forInvoice($invoice);
        if (GhMoney::cmp($balance['remaining_due'], '0') <= 0
            || $balance['payment_status'] === GhInvoicePaymentStatus::PAID) {
            return null;
        }

        if ($balance['payment_status'] === GhInvoicePaymentStatus::PARTIAL) {
            return $this->warning(
                self::CODE_COMPLETED_PARTIAL,
                'red',
                __('gh.warnings.completed_partial'),
            );
        }

        return $this->warning(
            self::CODE_COMPLETED_UNPAID,
            'red',
            __('gh.warnings.completed_unpaid'),
        );
    }

    /**
     * @return array{code: string, tone: 'orange'|'red', label: string}
     */
    private function warning(string $code, string $tone, string $label): array
    {
        return [
            'code' => $code,
            'tone' => $tone,
            'label' => $label,
        ];
    }
}
