<?php

namespace App\Services\Attendance;

use App\Exceptions\ShiftAssignmentException;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TL / Manager daily rotation + EXTRA / OT pre-assignment (T-2.14).
 */
class ShiftAssignmentService
{
    public function __construct(private readonly TeamMembershipService $membership) {}

    /**
     * @param  array{
     *     user_id: int,
     *     date: string,
     *     shift_type: string,
     *     expected_start_time: string,
     *     expected_end_time: string,
     *     expected_hours: float|int|string,
     *     multiplier?: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function assign(User $actor, array $data): ShiftAssignment
    {
        $employee = User::query()->findOrFail($data['user_id']);

        if (! $this->actorMayAssignTo($actor, $employee)) {
            throw ShiftAssignmentException::employeeNotAssignable();
        }

        $date = Carbon::parse($data['date'])->toDateString();
        $shiftType = $data['shift_type'];

        $duplicate = ShiftAssignment::query()
            ->where('user_id', $employee->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->exists();

        if ($duplicate) {
            throw ShiftAssignmentException::duplicateScheduled();
        }

        $multiplier = $data['multiplier']
            ?? (in_array($shiftType, ShiftAssignment::PREMIUM_TYPES, true) ? 1.5 : 1.0);

        return DB::transaction(function () use ($actor, $employee, $date, $shiftType, $data, $multiplier) {
            return ShiftAssignment::create([
                'user_id' => $employee->id,
                'assigned_by_tl_id' => $actor->id,
                'date' => $date,
                'shift_type' => $shiftType,
                'expected_start_time' => $data['expected_start_time'],
                'expected_end_time' => $data['expected_end_time'],
                'expected_hours' => $data['expected_hours'],
                'multiplier' => $multiplier,
                'notes' => $data['notes'] ?? null,
                'status' => ShiftAssignment::STATUS_SCHEDULED,
            ]);
        });
    }

    public function cancel(ShiftAssignment $assignment): ShiftAssignment
    {
        if ($assignment->status !== ShiftAssignment::STATUS_SCHEDULED) {
            throw ShiftAssignmentException::notCancellable();
        }

        $assignment->update(['status' => ShiftAssignment::STATUS_CANCELLED]);

        return $assignment->refresh();
    }

    /**
     * @param  array{
     *     user_id: int,
     *     date: string,
     *     shift_type: string,
     *     expected_start_time: string,
     *     expected_end_time: string,
     *     expected_hours: float|int|string,
     *     multiplier?: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function update(User $actor, ShiftAssignment $assignment, array $data): ShiftAssignment
    {
        if ($assignment->status !== ShiftAssignment::STATUS_SCHEDULED) {
            throw ShiftAssignmentException::notEditable();
        }

        $employee = User::query()->findOrFail($data['user_id']);

        if (! $this->actorMayAssignTo($actor, $employee)) {
            throw ShiftAssignmentException::employeeNotAssignable();
        }

        $date = Carbon::parse($data['date'])->toDateString();
        $shiftType = $data['shift_type'];

        $duplicate = ShiftAssignment::query()
            ->where('user_id', $employee->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->where('id', '!=', $assignment->id)
            ->exists();

        if ($duplicate) {
            throw ShiftAssignmentException::duplicateScheduled();
        }

        $multiplier = $data['multiplier']
            ?? (in_array($shiftType, ShiftAssignment::PREMIUM_TYPES, true) ? 1.5 : 1.0);

        return DB::transaction(function () use ($assignment, $employee, $date, $shiftType, $data, $multiplier) {
            $assignment->update([
                'user_id' => $employee->id,
                'date' => $date,
                'shift_type' => $shiftType,
                'expected_start_time' => $data['expected_start_time'],
                'expected_end_time' => $data['expected_end_time'],
                'expected_hours' => $data['expected_hours'],
                'multiplier' => $multiplier,
                'notes' => $data['notes'] ?? null,
            ]);

            return $assignment->refresh();
        });
    }

    /** @return Collection<int, array{employee: User, assignments: Collection<int, ShiftAssignment>}> */
    public function teamRosterForDate(User $actor, string $date): Collection
    {
        $date = Carbon::parse($date)->toDateString();
        $employees = $this->assignableEmployees($actor);

        $byUser = ShiftAssignment::query()
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereDate('date', $date)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->orderBy('shift_type')
            ->get()
            ->groupBy('user_id');

        return $employees->map(fn (User $employee) => [
            'employee' => $employee,
            'assignments' => $byUser->get($employee->id, collect()),
        ])->values();
    }

    public function listForActor(User $actor, ?string $date = null): LengthAwarePaginator
    {
        $query = ShiftAssignment::query()
            ->with(['user', 'assignedBy'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($date !== null && $date !== '') {
            $query->whereDate('date', Carbon::parse($date)->toDateString());
        }

        if ($actor->isTeamLeader()) {
            $query->whereHas('user', fn ($q) => $q->ledBy($actor->id));
        }

        return $query->paginate(25)->withQueryString();
    }

    /** @return EloquentCollection<int, User> */
    public function assignableEmployees(User $actor): EloquentCollection
    {
        if ($actor->canManageOperations()) {
            return User::query()
                ->whereIn('role', User::operationsVisibleRoles())
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name_en')
                ->get();
        }

        return User::query()
            ->whereIn('role', User::fieldStaffRoles())
            ->where('status', User::STATUS_ACTIVE)
            ->ledBy($actor->id)
            ->orderBy('name_en')
            ->get();
    }

    public function actorMayAssignTo(User $actor, User $target): bool
    {
        if (! $target->isActive()) {
            return false;
        }

        if ($actor->canManageOperations()) {
            return $target->isFieldStaff() || $target->isTeamLeader();
        }

        return $actor->isTeamLeader()
            && $target->isFieldStaff()
            && $this->membership->leads($actor, $target);
    }
}
