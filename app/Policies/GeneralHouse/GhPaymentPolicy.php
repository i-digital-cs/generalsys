<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;

/**
 * GH-6: Manager finance routes remain Manager-only.
 * Driver one-step delivery collection is authorized in logistics (not these Gates).
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

    public function confirmDeclaration(User $user, GhPayment $payment): bool
    {
        return GhCatalogAccess::mayManage($user)
            && GhLaunchConfig::cashEnabled()
            && $payment->method === GhPaymentMethod::CASH
            && $payment->status === GhPaymentStatus::PENDING_REVIEW;
    }

    public function cancelPending(User $user, GhPayment $payment): bool
    {
        return GhCatalogAccess::mayManage($user)
            && in_array($payment->status, [GhPaymentStatus::PENDING, GhPaymentStatus::PENDING_REVIEW], true);
    }
}
