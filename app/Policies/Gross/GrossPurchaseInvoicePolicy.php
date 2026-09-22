<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\User;
use App\Support\GrossAccess;

/**
 * Purchases: create/confirm/returns = Manager + Accountant.
 * Update / delete (any status) = Manager (Admin) only.
 */
class GrossPurchaseInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function view(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutatePurchaseInvoice($user);
    }

    public function delete(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutatePurchaseInvoice($user);
    }

    public function confirm(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function cancel(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutatePurchaseInvoice($user) && $invoice->isDraft();
    }

    public function returnLines(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayManageMasterData($user) && $invoice->isConfirmed();
    }
}
