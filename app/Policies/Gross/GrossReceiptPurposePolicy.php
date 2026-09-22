<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossReceiptPurpose;
use App\Models\User;
use App\Support\GrossAccess;

class GrossReceiptPurposePolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossReceiptPurpose $purpose): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function delete(User $user, GrossReceiptPurpose $purpose): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }
}
