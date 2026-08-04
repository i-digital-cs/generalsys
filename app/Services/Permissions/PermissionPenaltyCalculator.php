<?php

namespace App\Services\Permissions;

use App\Models\PenaltyRule;
use App\Models\Permission;
use App\Models\User;
use App\Services\Payroll\PenaltyRuleService;
use Carbon\Carbon;

/**
 * Minutes × per-minute rate from active penalty_rules (config fallback).
 */
class PermissionPenaltyCalculator
{
    public function __construct(private readonly PenaltyRuleService $rules) {}

    public function minutes(Permission $permission): int
    {
        $from = Carbon::createFromFormat('H:i', $permission->from_time);
        $to = Carbon::createFromFormat('H:i', $permission->to_time);

        if ($to->lte($from)) {
            $to->addDay();
        }

        return (int) $from->diffInMinutes($to);
    }

    public function ratePerMinute(User $employee, string $permissionType): float
    {
        $rule = $this->activeRule($employee, $permissionType);
        if ($rule !== null) {
            return (float) $rule->amount_per_unit;
        }

        $currency = $employee->currency ?: 'EGP';
        $rates = config('penalties.'.$currency, config('penalties.EGP'));

        return (float) ($rates[$permissionType] ?? 0);
    }

    public function doubleMultiplier(User $employee, string $permissionType): float
    {
        $rule = $this->activeRule($employee, $permissionType);
        if ($rule !== null) {
            return (float) $rule->double_penalty_multiplier;
        }

        return (float) config('penalties.double_penalty_multiplier', 2.0);
    }

    public function amountForRejection(Permission $permission): float
    {
        $permission->loadMissing('user');
        $user = $permission->user;

        if ($user === null) {
            return 0.0;
        }

        $minutes = $this->minutes($permission);
        $rate = $this->ratePerMinute($user, $permission->type);

        return round($minutes * $rate, 2);
    }

    private function activeRule(User $employee, string $permissionType): ?PenaltyRule
    {
        $currency = $employee->currency ?: 'EGP';

        return $this->rules->findActive($permissionType, $currency);
    }
}
