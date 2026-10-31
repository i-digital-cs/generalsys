<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhLaunchConfig;

/**
 * GH-6/GH-9.2: Manager-only finance. Drivers never inherit payment privileges.
 * Launch flags are enforced here and again in GhPaymentService (fail closed).
 */
class GhPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhPayment $payment): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function viewForInvoice(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function recordCash(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user)
            && GhLaunchConfig::cashEnabled()
            && $invoice->isIssued();
    }

    public function initiateOnline(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user)
            && GhLaunchConfig::onlineInitiationEnabled()
            && $invoice->isIssued();
    }

    public function cancelPending(User $user, GhPayment $payment): bool
    {
        return GhCatalogAccess::mayManage($user) && $payment->isPending();
    }
}
