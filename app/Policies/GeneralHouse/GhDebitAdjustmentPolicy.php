<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhDebitAdjustment;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhLaunchConfig;

class GhDebitAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user) && GhLaunchConfig::debitAdjustmentsEnabled();
    }

    public function view(User $user, GhDebitAdjustment $adjustment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?GhInvoice $invoice = null): bool
    {
        return $this->viewAny($user);
    }

    public function issue(User $user, GhDebitAdjustment $adjustment): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, GhDebitAdjustment $adjustment): bool
    {
        return $this->viewAny($user);
    }
}
