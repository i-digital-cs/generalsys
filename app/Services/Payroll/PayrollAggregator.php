<?php

namespace App\Services\Payroll;

use App\Models\Advance;
use App\Models\Attendance;
use App\Models\PayrollAdjustment;
use App\Models\Permission;
use App\Models\User;
use App\Services\Employees\RestDayService;
use App\Services\Permissions\PermissionPenaltyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance / permission / advance inputs for one employee-month (T-4.4).
 * Rest-day pool (Rest Days screen) feeds rest_days counts on generate/recalc.
 */
class PayrollAggregator
{
    private const WORKED = [
        Attendance::STATUS_PRESENT,
        Attendance::STATUS_LATE,
        Attendance::STATUS_EARLY_LEAVE,
    ];

    public function __construct(
        private readonly PermissionPenaltyCalculator $permissions,
        private readonly RestDayService $restDays,
    ) {}

    /**
     * @return array{
     *     full_time_days: int,
     *     online_half_days: int,
     *     online_full_days: int,
     *     rest_days: int,
     *     excused_absence_days: int,
     *     unexcused_absence_days: int,
     *     official_holiday_work_days: int,
     *     friday_full_time_days: int,
     *     friday_online_days: int,
     *     day_off_full_time_days: int,
     *     day_off_online_days: int,
     *     friday_work_days: int,
     *     day_off_work_days: int,
     *     extra_shift_hours: float,
     *     overtime_hours: float,
     *     hours_deduction: float,
     *     late_minutes_total: int,
     *     leave_permission_minutes: int,
     *     late_permission_minutes: int,
     *     forced_permission_penalty_base: float,
     *     adjustments: list<array{type: string, amount: float}>
     * }
     */
    public function aggregate(User $user, int $month, int $year): array
    {
        $rows = Attendance::query()
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $poolRestDates = $this->restDays->datesInMonth($user, $month, $year);
        $counts = $this->dayCounts($rows, $poolRestDates);
        $hours = $this->hoursDeduction($rows);
        $permission = $this->permissionTotals($user, $month, $year);

        return array_merge($counts, [
            'hours_deduction' => $hours,
            'late_minutes_total' => (int) $rows->sum('late_minutes'),
            'leave_permission_minutes' => $permission['leave_minutes'],
            'late_permission_minutes' => $permission['late_minutes'],
            'forced_permission_penalty_base' => $permission['forced_base'],
            'adjustments' => $this->advanceAdjustments($user),
        ]);
    }

    public function classifiedDayCount(User $user, int $month, int $year): int
    {
        $attendanceDates = Attendance::query()
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $restDates = $this->restDays->datesInMonth($user, $month, $year);

        return count(array_unique([...$attendanceDates, ...$restDates]));
    }

    /**
     * Excel G5–G9 style tallies for the payslip attendance panel.
     *
     * @return array{
     *     vacation_days: int,
     *     official_holiday_days: int,
     *     excused_absence_days: int,
     *     unexcused_absence_days: int
     * }
     */
    public function monthStatusSummary(User $user, int $month, int $year): array
    {
        $rows = Attendance::query()
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'status']);

        $vacation = [];
        $holiday = [];
        $excused = [];
        $unexcused = [];

        foreach ($rows as $row) {
            $key = $row->date->toDateString();
            match ($row->status) {
                Attendance::STATUS_VACATION => $vacation[$key] = true,
                Attendance::STATUS_OFFICIAL_HOLIDAY => $holiday[$key] = true,
                Attendance::STATUS_EXCUSED_ABSENCE => $excused[$key] = true,
                Attendance::STATUS_UNEXCUSED_ABSENCE => $unexcused[$key] = true,
                default => null,
            };
        }

        return [
            'vacation_days' => count($vacation),
            'official_holiday_days' => count($holiday),
            'excused_absence_days' => count($excused),
            'unexcused_absence_days' => count($unexcused),
        ];
    }

    public function expectedDayCount(int $month, int $year): int
    {
        return Carbon::create($year, $month, 1)->daysInMonth;
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     * @param  list<string>  $poolRestDates  Y-m-d from Rest Days screen
     * @return array<string, int|float>
     */
    public function dayCounts($rows, array $poolRestDates = []): array
    {
        $ft = 0;
        $half = 0;
        $full = 0;
        $friFt = 0;
        $friOnline = 0;
        $offFt = 0;
        $offOnline = 0;
        $extra = 0.0;
        $ot = 0.0;

        $restDates = array_fill_keys($poolRestDates, true);
        $excusedDates = [];
        $unexcusedDates = [];
        $holidayDates = [];

        foreach ($rows as $row) {
            $dateKey = $row->date->toDateString();

            if (in_array($row->status, self::WORKED, true)) {
                if ($row->shift_type === Attendance::SHIFT_FULL_TIME) {
                    $ft++;
                    if ($row->is_friday_work) {
                        $friFt++;
                    }
                    if ($row->is_day_off_work) {
                        $offFt++;
                    }
                } elseif ($row->shift_type === Attendance::SHIFT_ONLINE_HALF) {
                    $half++;
                    if ($row->is_friday_work) {
                        $friOnline++;
                    }
                    if ($row->is_day_off_work) {
                        $offOnline++;
                    }
                } elseif ($row->shift_type === Attendance::SHIFT_ONLINE_FULL) {
                    $full++;
                    if ($row->is_friday_work) {
                        $friOnline++;
                    }
                    if ($row->is_day_off_work) {
                        $offOnline++;
                    }
                } elseif ($row->shift_type === Attendance::SHIFT_EXTRA) {
                    $extra += (float) $row->actual_hours;
                } elseif ($row->shift_type === Attendance::SHIFT_OVERTIME) {
                    $ot += (float) $row->actual_hours;
                }

                if ($row->is_official_holiday_work) {
                    $holidayDates[$dateKey] = true;
                }
            }

            if ($row->status === Attendance::STATUS_REST_DAY) {
                $restDates[$dateKey] = true;
            } elseif ($row->status === Attendance::STATUS_EXCUSED_ABSENCE) {
                $excusedDates[$dateKey] = true;
            } elseif ($row->status === Attendance::STATUS_UNEXCUSED_ABSENCE) {
                $unexcusedDates[$dateKey] = true;
            } elseif ($row->status === Attendance::STATUS_OFFICIAL_HOLIDAY && $row->is_official_holiday_work) {
                $holidayDates[$dateKey] = true;
            }
        }

        // Rest-day pool dates with no clock stay as rest. Worked-on-rest (day-off work)
        // stays in FT/online counts and still counts toward rest_days (planned day off).
        $holidayWork = count($holidayDates);

        return [
            'full_time_days' => $ft,
            'online_half_days' => $half,
            'online_full_days' => $full,
            'rest_days' => count($restDates),
            'excused_absence_days' => count($excusedDates),
            'unexcused_absence_days' => count($unexcusedDates),
            'official_holiday_work_days' => $holidayWork,
            'friday_full_time_days' => $friFt,
            'friday_online_days' => $friOnline,
            'day_off_full_time_days' => $offFt,
            'day_off_online_days' => $offOnline,
            'friday_work_days' => $friFt + $friOnline,
            'day_off_work_days' => $offFt + $offOnline,
            'extra_shift_hours' => round($extra, 2),
            'overtime_hours' => round($ot, 2),
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function hoursDeduction($rows): float
    {
        $planned = 0.0;
        $actual = 0.0;

        foreach ($rows as $row) {
            if (! in_array($row->status, self::WORKED, true)) {
                continue;
            }
            if (! in_array($row->shift_type, [
                Attendance::SHIFT_FULL_TIME,
                Attendance::SHIFT_ONLINE_HALF,
                Attendance::SHIFT_ONLINE_FULL,
            ], true)) {
                continue;
            }

            $planned += (float) $row->planned_hours;
            $actual += (float) $row->actual_hours;
        }

        return round(max(0, $planned - $actual), 2);
    }

    /**
     * @return array{leave_minutes: int, late_minutes: int, forced_base: float}
     */
    private function permissionTotals(User $user, int $month, int $year): array
    {
        $rows = Permission::query()
            ->where('user_id', $user->id)
            ->whereYear('request_date', $year)
            ->whereMonth('request_date', $month)
            ->whereIn('status', [Permission::STATUS_REJECTED, Permission::STATUS_FORCED])
            ->get();

        $leave = 0;
        $late = 0;
        $forced = 0.0;

        foreach ($rows as $row) {
            $minutes = $this->permissions->minutes($row);
            if ($row->type === Permission::TYPE_LEAVE) {
                $leave += $minutes;
            } else {
                $late += $minutes;
            }
            if ($row->status === Permission::STATUS_FORCED) {
                $forced += (float) $row->penalty_amount;
            }
        }

        return [
            'leave_minutes' => $leave,
            'late_minutes' => $late,
            'forced_base' => round($forced, 2),
        ];
    }

    /**
     * @return list<array{type: string, amount: float}>
     */
    private function advanceAdjustments(User $user): array
    {
        $advances = Advance::query()
            ->where('user_id', $user->id)
            ->where('status', Advance::STATUS_ACTIVE)
            ->where('remaining_amount', '>', 0)
            ->get();

        $out = [];
        foreach ($advances as $advance) {
            $amount = min(
                (float) $advance->deduction_amount_monthly,
                (float) $advance->remaining_amount,
            );
            if ($amount <= 0) {
                continue;
            }
            $out[] = [
                'type' => PayrollAdjustment::TYPE_ADVANCE,
                'amount' => round($amount, 2),
            ];
        }

        return $out;
    }
}
