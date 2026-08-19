<?php

namespace App\Services\Valuation;

use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ValuationAssignmentService
{
    public function isAssigned(User $user, string $module): bool
    {
        return ValuationAssignment::query()
            ->where('user_id', $user->id)
            ->where('module', $module)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function modulesFor(User $user): array
    {
        return ValuationAssignment::query()
            ->where('user_id', $user->id)
            ->orderBy('module')
            ->pluck('module')
            ->all();
    }

    /**
     * @param  list<string>  $modules
     */
    public function syncForUser(User $actor, User $user, array $modules): void
    {
        $modules = array_values(array_intersect($modules, ValuationAssignment::MODULES));

        DB::transaction(function () use ($actor, $user, $modules): void {
            ValuationAssignment::query()->where('user_id', $user->id)->delete();

            foreach ($modules as $module) {
                ValuationAssignment::query()->create([
                    'user_id' => $user->id,
                    'assigned_by' => $actor->id,
                    'module' => $module,
                ]);
            }
        });
    }

    public function paginateAssignees(int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->with(['valuationAssignments'])
            ->orderBy('name_en')
            ->paginate($perPage);
    }
}
