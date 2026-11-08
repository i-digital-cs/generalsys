<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhFinancialCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhFinancialCorrection $correction): bool
    {
        return $this->viewAny($user);
    }

    public function request(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function submit(User $user, GhFinancialCorrection $correction): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, GhFinancialCorrection $correction): bool
    {
        return $this->viewAny($user);
    }

    public function reject(User $user, GhFinancialCorrection $correction): bool
    {
        return $this->viewAny($user);
    }

    public function execute(User $user, GhFinancialCorrection $correction): bool
    {
        return $this->viewAny($user);
    }
}
