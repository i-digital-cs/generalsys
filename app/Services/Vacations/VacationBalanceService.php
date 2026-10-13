<?php

namespace App\Services\Vacations;

use App\Exceptions\VacationRequestException;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Models\VacationCreditLog;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-year vacation balance (accrued / pending / used / remaining) + TL credit.
 */
class VacationBalanceService
{
    public function __construct(
        private readonly TeamMembershipService $membership,
    ) {}

    public static function usesBalance(string $type): bool
    {
        return in_array($type, [Vacation::TYPE_ANNUAL, Vacation::TYPE_SICK], true);
    }

    public function balanceFor(User $user, ?int $year = null): VacationBalance
    {
        $year ??= (int) now()->format('Y');

        return VacationBalance::query()->firstOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            [
                'accrued_days' => 0,
                'used_days' => 0,
                'pending_days' => 0,
                'remaining_days' => 0,
            ],
        );
    }

    /**
     * Load (or create) the year balance under row lock — call inside a transaction.
     */
    public function lockedBalanceFor(User $user, int $year): VacationBalance
    {
        $this->balanceFor($user, $year);

        /** @var VacationBalance $balance */
        $balance = VacationBalance::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        return $balance;
    }

    public function assertCanReserve(User $user, float $days, int $year): void
    {
        $balance = $this->balanceFor($user, $year);
        if ((float) $balance->remaining_days + 0.0001 < round($days, 1)) {
            throw VacationRequestException::insufficientBalance();
        }
    }

    public function reserveForVacation(Vacation $vacation): void
    {
        if (! self::usesBalance($vacation->type)) {
            return;
        }

        $user = $vacation->user ?? User::query()->findOrFail($vacation->user_id);
        $year = (int) $vacation->start_date->format('Y');
        $days = round((float) $vacation->days_requested, 1);

        $balance = $this->lockedBalanceFor($user, $year);
        if ((float) $balance->remaining_days + 0.0001 < $days) {
            throw VacationRequestException::insufficientBalance();
        }

        $balance->pending_days = round((float) $balance->pending_days + $days, 1);
        $this->syncRemaining($balance);
        $balance->save();
    }

    public function releaseForVacation(Vacation $vacation): void
    {
        if (! self::usesBalance($vacation->type)) {
            return;
        }

        $user = $vacation->user ?? User::query()->findOrFail($vacation->user_id);
        $year = (int) $vacation->start_date->format('Y');
        $days = round((float) $vacation->days_requested, 1);
        $balance = $this->lockedBalanceFor($user, $year);
        $balance->pending_days = round(max(0, (float) $balance->pending_days - $days), 1);
        $this->syncRemaining($balance);
        $balance->save();
    }

    public function confirmForVacation(Vacation $vacation): void
    {
        if (! self::usesBalance($vacation->type)) {
            return;
        }

        $user = $vacation->user ?? User::query()->findOrFail($vacation->user_id);
        $year = (int) $vacation->start_date->format('Y');
        $days = round((float) $vacation->days_requested, 1);
        $balance = $this->lockedBalanceFor($user, $year);
        $balance->pending_days = round(max(0, (float) $balance->pending_days - $days), 1);
        $balance->used_days = round((float) $balance->used_days + $days, 1);
        $this->syncRemaining($balance);
        $balance->save();
    }

    /** After REJECTED → FORCED when pending was already released. */
    public function chargeForcedVacation(Vacation $vacation): void
    {
        if (! self::usesBalance($vacation->type)) {
            return;
        }

        $user = $vacation->user ?? User::query()->findOrFail($vacation->user_id);
        $year = (int) $vacation->start_date->format('Y');
        $days = round((float) $vacation->days_requested, 1);
        $balance = $this->lockedBalanceFor($user, $year);
        if ((float) $balance->remaining_days + 0.0001 < $days) {
            throw VacationRequestException::insufficientBalance();
        }
        $balance->used_days = round((float) $balance->used_days + $days, 1);
        $this->syncRemaining($balance);
        $balance->save();
    }

    public function addCredit(User $actor, User $employee, float $amountDays, ?string $reason, ?int $year = null): VacationCreditLog
    {
        if (! $this->actorMayManage($actor, $employee)) {
            throw VacationRequestException::cannotManageCredit();
        }

        $amountDays = round($amountDays, 1);
        if ($amountDays < 0.5) {
            throw VacationRequestException::invalidCreditAmount();
        }

        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($actor, $employee, $amountDays, $reason, $year) {
            $balance = $this->lockedBalanceFor($employee, $year);
            $balance->accrued_days = round((float) $balance->accrued_days + $amountDays, 1);
            $balance->last_updated_by = $actor->id;
            $this->syncRemaining($balance);
            $balance->save();

            return VacationCreditLog::query()->create([
                'vacation_balance_id' => $balance->id,
                'added_by' => $actor->id,
                'amount_days' => $amountDays,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        });
    }

    /** @return Collection<int, User> */
    public function manageableEmployees(User $actor): Collection
    {
        $year = (int) now()->format('Y');

        if ($actor->canManageOperations()) {
            return User::query()
                ->whereIn('role', User::operationsVisibleRoles())
                ->where('status', User::STATUS_ACTIVE)
                ->with(['vacationBalances' => fn ($q) => $q->where('year', $year)])
                ->orderBy('name_en')
                ->get();
        }

        if ($actor->isTeamLeader()) {
            return User::query()
                ->whereIn('role', User::fieldStaffRoles())
                ->ledBy($actor->id)
                ->where('status', User::STATUS_ACTIVE)
                ->with(['vacationBalances' => fn ($q) => $q->where('year', $year)])
                ->orderBy('name_en')
                ->get();
        }

        return collect();
    }

    public function actorMayManage(User $actor, User $employee): bool
    {
        if ($actor->canManageOperations()) {
            return in_array($employee->role, User::operationsVisibleRoles(), true);
        }

        return $actor->isTeamLeader()
            && in_array($employee->role, User::fieldStaffRoles(), true)
            && $this->membership->leads($actor, $employee);
    }

    private function syncRemaining(VacationBalance $balance): void
    {
        $balance->remaining_days = round(
            max(0, (float) $balance->accrued_days - (float) $balance->used_days - (float) $balance->pending_days),
            1,
        );
    }
}
