<?php

namespace App\Services\Hr;

use App\Exceptions\PermissionRequestException;
use App\Exceptions\VacationRequestException;
use App\Models\Permission;
use App\Models\Vacation;

/**
 * Authoritative pending/rejected transition boundary for HR leave & vacation.
 *
 * Controllers/policies still authorize actors; this guard prevents double
 * approve/reject/force (and double balance / attendance side-effects) by
 * locking the row and re-checking status inside the caller's transaction.
 */
final class HrRequestWriteGuard
{
    /**
     * @throws PermissionRequestException
     */
    public function lockPendingPermission(Permission $permission): Permission
    {
        /** @var Permission $locked */
        $locked = Permission::query()
            ->whereKey($permission->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== Permission::STATUS_PENDING) {
            throw PermissionRequestException::notPending();
        }

        return $locked;
    }

    /**
     * @throws PermissionRequestException
     */
    public function lockRejectedPermission(Permission $permission): Permission
    {
        /** @var Permission $locked */
        $locked = Permission::query()
            ->whereKey($permission->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== Permission::STATUS_REJECTED) {
            throw PermissionRequestException::notRejected();
        }

        return $locked;
    }

    /**
     * @throws VacationRequestException
     */
    public function lockPendingVacation(Vacation $vacation): Vacation
    {
        /** @var Vacation $locked */
        $locked = Vacation::query()
            ->whereKey($vacation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== Vacation::STATUS_PENDING) {
            throw VacationRequestException::notPending();
        }

        return $locked;
    }

    /**
     * @throws VacationRequestException
     */
    public function lockRejectedVacation(Vacation $vacation): Vacation
    {
        /** @var Vacation $locked */
        $locked = Vacation::query()
            ->whereKey($vacation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== Vacation::STATUS_REJECTED) {
            throw VacationRequestException::notRejected();
        }

        return $locked;
    }
}
