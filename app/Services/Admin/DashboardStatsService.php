<?php

namespace App\Services\Admin;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin dashboard stats — pure aggregate SQL, no loops over collections.
 */
class DashboardStatsService
{
    /**
     * @return array{total: int, active: int, inactive: int, contracts_expiring: int}
     */
    public function stats(): array
    {
        $staffRoles = [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER];

        return [
            'total' => User::whereIn('role', $staffRoles)->count(),
            'active' => User::whereIn('role', $staffRoles)->where('status', User::STATUS_ACTIVE)->count(),
            'inactive' => User::whereIn('role', $staffRoles)->where('status', User::STATUS_INACTIVE)->count(),
            'contracts_expiring' => Contract::where('is_active', true)
                ->whereBetween('contract_end', [now()->toDateString(), now()->addDays(60)->toDateString()])
                ->count(),
        ];
    }

    /**
     * Active contracts ending within 60 days, soonest first (user eager-loaded).
     *
     * @return Collection<int, Contract>
     */
    public function expiringContracts(int $limit = 5): Collection
    {
        return Contract::with('user')
            ->where('is_active', true)
            ->whereBetween('contract_end', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->orderBy('contract_end')
            ->limit($limit)
            ->get();
    }
}
