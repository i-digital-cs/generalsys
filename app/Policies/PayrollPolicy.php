<?php

namespace App\Policies;

use App\Models\PayrollRecord;
use App\Models\User;

/**
 * Payroll privacy (AGENTS §5.1): TEAM_LEADER → 403 on every payroll action.
 * Employee: own APPROVED/PAID only. Manager: full access.
 */
class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    /** Employee list of own published payslips (T-4.7). */
    public function viewAnyOwn(User $user): bool
    {
        return $user->isEmployee();
    }

    public function view(User $user, PayrollRecord $payroll): bool
    {
        if ($user->isManager()) {
            return true;
        }

        if ($user->isTeamLeader()) {
            return false;
        }

        return $user->id === $payroll->user_id
            && in_array($payroll->status, [
                PayrollRecord::STATUS_APPROVED,
                PayrollRecord::STATUS_PAID,
            ], true);
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, PayrollRecord $payroll): bool
    {
        return $user->isManager() && $payroll->status === PayrollRecord::STATUS_DRAFT;
    }

    public function approve(User $user, PayrollRecord $payroll): bool
    {
        return $user->isManager() && $payroll->status === PayrollRecord::STATUS_DRAFT;
    }

    public function generate(User $user): bool
    {
        return $user->isManager();
    }
}
