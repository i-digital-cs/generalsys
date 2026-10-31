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

    public static function overpaymentAllowed(): bool
    {
        return (bool) config('general_house.payments.overpayment_allowed', false);
    }

    public static function refundsEnabled(): bool
    {
        return (bool) config('general_house.payments.refunds_enabled', false);
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
