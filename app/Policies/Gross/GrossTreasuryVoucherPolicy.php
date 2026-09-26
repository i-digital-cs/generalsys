<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\User;
use App\Support\GrossAccess;

class GrossTreasuryVoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'treasury');
    }

    public function view(User $user, GrossTreasuryVoucher $voucher): bool
    {
        return GrossAccess::mayPage($user, 'treasury');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'treasury');
    }
}
