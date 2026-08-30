<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\User;
use App\Support\GrossAccess;

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
        return GrossAccess::mayManageMasterData($user) && $invoice->isDraft();
    }

    public function confirm(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function cancel(User $user, GrossPurchaseInvoice $invoice): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }
}
