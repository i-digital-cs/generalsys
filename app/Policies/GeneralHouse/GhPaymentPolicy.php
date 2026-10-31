<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

/**
 * GH-6: Manager-only finance. Drivers never inherit payment privileges from DRIVER role.
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
        return GhCatalogAccess::mayManage($user) && $invoice->isIssued();
    }

    public function initiateOnline(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user) && $invoice->isIssued();
    }

    public function cancelPending(User $user, GhPayment $payment): bool
    {
        return GhCatalogAccess::mayManage($user) && $payment->isPending();
    }
}
