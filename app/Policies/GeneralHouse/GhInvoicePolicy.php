<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user) && $invoice->isDraft();
    }

    public function issue(User $user, GhInvoice $invoice): bool
    {
        return GhCatalogAccess::mayManage($user) && $invoice->isDraft();
    }
}
