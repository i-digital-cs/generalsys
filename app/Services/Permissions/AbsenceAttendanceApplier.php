<?php

namespace App\Services\Permissions;

use App\Exceptions\PermissionRequestException;
use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\Permission;
use App\Models\User;
use App\Services\Attendance\ShiftConfig;
use App\Services\Employees\RestDayService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Marks working day(s) as excused absence for payroll (T-15.1).
 * Skips rest days, official holidays, and approved vacation days.
 */
class AbsenceAttendanceApplier
{
    /** Days that already have work / unexcused absence — block the whole apply. */
    private const BLOCKING_STATUSES = [
        Attendance::STATUS_PRESENT,
        Attendance::STATUS_LATE,
        Attendance::STATUS_EARLY_LEAVE,
        Attendance::STATUS_UNEXCUSED_ABSENCE,
    ];

    /** Non-working attendance rows — skip (do not write EXCUSED_ABSENCE). */
    private const SKIP_STATUSES = [
        Attendance::STATUS_REST_DAY,
        Attendance::STATUS_OFFICIAL_HOLIDAY,
        Attendance::STATUS_VACATION,
    ];

    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly RestDayService $restDays,
    ) {}

    /**
     * Apply EXCUSED_ABSENCE for each working day in the permission range (all-or-nothing).
     *
     * @return list<Attendance>
     */
    public function apply(Permission $permission): array
    {
        if (! $permission->isAbsence()) {
            throw PermissionRequestException::invalidAbsence();
        }

        $permission->loadMissing('user');
        $user = $permission->user;
        if ($user === null) {
            throw PermissionRequestException::invalidAbsence();
        }

        $start = Carbon::parse($permission->request_date)->startOfDay();
        $end = Carbon::parse($permission->end_date ?? $permission->request_date)->startOfDay();
        if ($end->lt($start)) {
            throw PermissionRequestException::invalidAbsence();
        }

        $from = $start->toDateString();
        $to = $end->toDateString();
        $vacationDates = array_fill_keys(
            $this->restDays->approvedVacationDates($user, $from, $to),
            true
        );
        $holidayDates = array_fill_keys(
            OfficialHoliday::query()
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all(),
            true
        );

        $shiftType = $this->resolveShiftType($user);
        $planned = $this->shifts->resolvePlannedHours($user, $shiftType);

        return DB::transaction(function () use ($user, $start, $end, $shiftType, $planned, $vacationDates, $holidayDates) {
            $applied = [];

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $date = $day->toDateString();

                if ($this->shouldSkipNonWorkingDay($user, $date, $vacationDates, $holidayDates)) {
                    continue;
                }

                $row = $this->applyOneDay($user, $date, $shiftType, $planned);
                if ($row !== null) {
                    $applied[] = $row;
                }
            }

            if ($applied === []) {
                throw PermissionRequestException::absenceNoWorkingDays();
            }

            return $applied;
        });
    }

    /**
     * @param  array<string, true>  $vacationDates
     * @param  array<string, true>  $holidayDates
     */
    private function shouldSkipNonWorkingDay(
        User $user,
        string $date,
        array $vacationDates,
        array $holidayDates,
    ): bool {
        if ($this->restDays->isRestDate($user, $date)) {
            return true;
        }

        if (isset($holidayDates[$date])) {
            return true;
        }

        if (isset($vacationDates[$date])) {
            return true;
        }

        return false;
    }

    private function applyOneDay(User $user, string $date, string $shiftType, float $planned): ?Attendance
    {
        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->lockForUpdate()
            ->get();

        foreach ($existing as $row) {
            if ($row->status === Attendance::STATUS_EXCUSED_ABSENCE) {
                return $row;
            }

            if (in_array($row->status, self::SKIP_STATUSES, true)) {
                return null;
            }

            if (in_array($row->status, self::BLOCKING_STATUSES, true)
                || $row->clock_in !== null
                || $row->segments()->exists()) {
                throw PermissionRequestException::dayAlreadyWorked($date);
            }
        }

        $row = $existing->firstWhere('shift_type', $shiftType);
        if ($row === null) {
            $row = new Attendance([
                'user_id' => $user->id,
                'date' => $date,
                'shift_type' => $shiftType,
            ]);
        }

        $row->fill([
            'planned_hours' => $planned,
            'actual_hours' => 0,
            'raw_actual_hours' => 0,
            'credited_hours' => 0,
            'status' => Attendance::STATUS_EXCUSED_ABSENCE,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
            'clock_in' => null,
            'clock_out' => null,
        ]);
        $row->save();

        return $row->fresh();
    }

    private function resolveShiftType(User $user): string
    {
        if (is_string($user->shift_type_default) && $user->shift_type_default !== '') {
            return $user->shift_type_default;
        }

        $types = $user->defaultShiftTypes();
        if ($types !== []) {
            return $types[0];
        }

        return Attendance::SHIFT_FULL_TIME;
    }
}
