<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ValuationSheet;

class ValuationSheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager() || $user->valuationAssignments()->exists();
    }

    public function view(User $user, ValuationSheet $sheet): bool
    {
        return $user->isManager() || $user->is($sheet->user);
    }

    public function create(User $user): bool
    {
        return $user->valuationAssignments()->exists();
    }

    public function update(User $user, ValuationSheet $sheet): bool
    {
        return $user->is($sheet->user);
    }
}
