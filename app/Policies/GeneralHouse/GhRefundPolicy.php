<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhRefund;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhLaunchConfig;

class GhRefundPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user) && GhLaunchConfig::refundsEnabled();
    }

    public function view(User $user, GhRefund $refund): bool
    {
        return $this->viewAny($user);
    }

    public function request(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function submit(User $user, GhRefund $refund): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, GhRefund $refund): bool
    {
        return $this->viewAny($user);
    }

    public function reject(User $user, GhRefund $refund): bool
    {
        return $this->viewAny($user);
    }

    public function payOut(User $user, GhRefund $refund): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, GhRefund $refund): bool
    {
        return $this->viewAny($user);
    }
}
