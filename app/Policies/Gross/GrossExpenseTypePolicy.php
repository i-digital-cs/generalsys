<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossExpenseType;
use App\Models\User;
use App\Support\GrossAccess;

class GrossExpenseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'expense_types');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'expense_types');
    }

    public function update(User $user, GrossExpenseType $type): bool
    {
        return GrossAccess::mayPage($user, 'expense_types');
    }

    public function delete(User $user, GrossExpenseType $type): bool
    {
        return GrossAccess::mayPage($user, 'expense_types');
    }
}
