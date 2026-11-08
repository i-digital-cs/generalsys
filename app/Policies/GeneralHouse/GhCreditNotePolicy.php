<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhLaunchConfig;

class GhCreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user) && GhLaunchConfig::creditNotesEnabled();
    }

    public function view(User $user, GhCreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?GhInvoice $invoice = null): bool
    {
        return $this->viewAny($user);
    }

    public function submit(User $user, GhCreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, GhCreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }

    public function reject(User $user, GhCreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }

    public function issue(User $user, GhCreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, GhCreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }
}
