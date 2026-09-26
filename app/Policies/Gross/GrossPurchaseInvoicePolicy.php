<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\User;
use App\Support\GrossAccess;

/**
 * Purchases: page `purchases`; update/delete = Manager only.
 */
class GrossPurchaseInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'purchases');
    }

    public function view(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayPage($user, 'purchases');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'purchases');
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
        return GrossAccess::mayPage($user, 'purchases');
    }

    public function cancel(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutatePurchaseInvoice($user) && $invoice->isDraft();
    }

    public function returnLines(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayPage($user, 'purchases') && $invoice->isConfirmed();
    }
}
