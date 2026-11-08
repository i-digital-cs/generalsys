<?php

namespace App\Support\GeneralHouse;

/**
 * Authoritative GH launch capability flags (config/general_house.php).
 * Controllers, services, policies, and Blade must read through this helper —
 * never hardcode launch defaults. Unconfigured payment capabilities fail closed.
 */
final class GhLaunchConfig
{
    public static function cashEnabled(): bool
    {
        return (bool) config('general_house.payments.cash_enabled', false);
    }

    public static function partialPaymentsEnabled(): bool
    {
        return (bool) config('general_house.payments.partial_payments_enabled', false);
    }

    public static function onlineInitiationEnabled(): bool
    {
        return (bool) config('general_house.payments.online_initiation_enabled', false);
    }

    public static function onlineGatewayConfirmEnabled(): bool
    {
        return (bool) config('general_house.payments.online_gateway_confirm_enabled', false);
    }

    public static function bankTransferEnabled(): bool
    {
        return (bool) config('general_house.payments.bank_transfer_enabled', false);
    }

    public static function driverCollectionEnabled(): bool
    {
        return (bool) config('general_house.payments.driver_collection_enabled', false);
    }

    /**
     * Driver one-step delivery payment (CASH/ONLINE → CONFIRMED).
     * Legacy alias: driver_payment_declaration_enabled.
     */
    public static function driverDeliveryPaymentEnabled(): bool
    {
        return (bool) config(
            'general_house.payments.driver_delivery_payment_enabled',
            config('general_house.payments.driver_payment_declaration_enabled', false)
        );
    }

    /** @deprecated Use driverDeliveryPaymentEnabled() */
    public static function driverPaymentDeclarationEnabled(): bool
    {
        return self::driverDeliveryPaymentEnabled();
    }

    public static function overpaymentAllowed(): bool
    {
        return (bool) config('general_house.payments.overpayment_allowed', false);
    }

    public static function refundsEnabled(): bool
    {
        // GH-10 cash-refund domain (Option B). Legacy payments.refunds_enabled stays false.
        return (bool) config('general_house.corrections.refunds_enabled', false);
    }

    public static function creditNotesEnabled(): bool
    {
        return (bool) config('general_house.corrections.credit_notes_enabled', false);
    }

    public static function paymentCorrectionExecuteEnabled(): bool
    {
        return (bool) config('general_house.corrections.payment_correction_execute_enabled', false);
    }

    public static function requireDistinctApprover(): bool
    {
        return (bool) config('general_house.corrections.require_distinct_approver', true);
    }

    public static function requireDistinctPayoutActor(): bool
    {
        return (bool) config('general_house.corrections.require_distinct_payout_actor', true);
    }

    /** When false, SURCHARGE policy never creates adjustments (VALIDATION_ONLY semantics). */
    public static function minOrderSurchargeEnabled(): bool
    {
        return (bool) config('general_house.min_order.surcharge_enabled', false);
    }

    public static function defaultMinOrderPolicy(): string
    {
        $policy = (string) config('general_house.min_order.default_policy', GhMinOrderPolicy::VALIDATION_ONLY);

        return in_array($policy, GhMinOrderPolicy::ALL, true)
            ? $policy
            : GhMinOrderPolicy::VALIDATION_ONLY;
    }
}
