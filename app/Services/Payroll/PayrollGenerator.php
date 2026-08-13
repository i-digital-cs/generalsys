<?php

namespace App\Services\Payroll;

use App\Jobs\GeneratePayrollJob;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Admin\SettingService;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates draft payroll creation (T-4.4) — validate → aggregate → calculate → upsert DRAFT.
 */
class PayrollGenerator
{
    public const CHUNK_SIZE = 100;

    public function __construct(
        private readonly PayrollAggregator $aggregator,
        private readonly PayrollCalculator $calculator,
        private readonly AdvanceService $advances,
        private readonly SettingService $settings,
    ) {}

    /**
     * Queue chunked generation jobs (≤ 100 employees per job).
     *
     * @return int Number of jobs dispatched
     */
    public function queueGeneration(
        int $month,
        int $year,
        User $actor,
        ?int $teamLeaderId = null,
    ): int {
        $ids = $this->eligibleUserIds($teamLeaderId);
        $dispatched = 0;

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            GeneratePayrollJob::dispatch($month, $year, $actor->id, $chunk);
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Generate drafts in-process (Hostinger-safe for small teams).
     * Incomplete attendance: warn (default) or skip when require_full_month_coverage.
     *
     * @return array{
     *     created: int,
     *     skipped: list<array{name: string, classified: int, expected: int}>,
     *     incomplete: list<array{name: string, classified: int, expected: int}>,
     *     first_id: int|null
     * }
     */
    public function generateMonthInline(
        int $month,
        int $year,
        User $actor,
        ?int $teamLeaderId = null,
    ): array {
        $created = 0;
        $skipped = [];
        $incomplete = [];
        $firstId = null;
        $strict = (bool) config('payroll.require_full_month_coverage', false);

        foreach ($this->eligibleUserIds($teamLeaderId) as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $coverage = $this->coverageGap($user, $month, $year);
            if ($coverage !== null) {
                $row = [
                    'name' => $user->name_en ?: $user->name_ar ?: $user->email,
                    'classified' => $coverage['classified'],
                    'expected' => $coverage['expected'],
                ];

                if ($strict) {
                    $skipped[] = $row;

                    continue;
                }

                $incomplete[] = $row;
            }

            $record = $this->generateForUser($user, $month, $year, $actor->id);
            $created++;
            $firstId ??= $record->id;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'incomplete' => $incomplete,
            'first_id' => $firstId,
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return list<PayrollRecord>
     */
    public function generateChunk(int $month, int $year, int $createdBy, array $userIds): array
    {
        $records = [];

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $records[] = $this->generateForUser($user, $month, $year, $createdBy);
        }

        return $records;
    }

    public function generateForUser(User $user, int $month, int $year, int $createdBy): PayrollRecord
    {
        if (config('payroll.require_full_month_coverage', false)) {
            $this->validateMonthCoverage($user, $month, $year);
        }

        $existing = PayrollRecord::query()
            ->where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing !== null && $existing->status !== PayrollRecord::STATUS_DRAFT) {
            return $existing;
        }

        $agg = $this->aggregator->aggregate($user, $month, $year);
        $extraDays = (int) ($existing?->extra_days ?? 0);
        $extraPay = (float) ($existing?->extra_pay ?? 0);
        $otMult = (float) ($existing?->overtime_multiplier ?? 0);
        if ($otMult <= 0) {
            $otMult = $this->settings->multiplier('overtime');
        }
        $input = $this->toInput($user, $agg, $extraDays, $extraPay, $otMult);
        $result = $this->calculator->calculate($input);

        $attrs = array_merge(
            [
                'user_id' => $user->id,
                'month' => $month,
                'year' => $year,
                'currency' => $user->currency ?: 'EGP',
                'status' => PayrollRecord::STATUS_DRAFT,
                'created_by' => $createdBy,
                'full_time_days' => $agg['full_time_days'],
                'online_half_days' => $agg['online_half_days'],
                'online_full_days' => $agg['online_full_days'],
                'rest_days' => $agg['rest_days'],
                'extra_shift_hours' => 0,
                'overtime_hours' => round(
                    (float) $agg['overtime_hours'] + (float) $agg['extra_shift_hours'],
                    2,
                ),
                'overtime_multiplier' => $otMult,
                'friday_work_days' => $agg['friday_work_days'],
                'day_off_work_days' => $agg['day_off_work_days'],
                'official_holiday_work_days' => $agg['official_holiday_work_days'],
                'extra_days' => $extraDays,
                'hours_deduction' => $agg['hours_deduction'],
                'excused_absence_days' => (int) ($agg['excused_absence_days'] ?? 0),
                'unexcused_absence_days' => (int) ($agg['unexcused_absence_days'] ?? 0),
                'late_minutes_total' => $agg['late_minutes_total'],
            ],
            $result->toPayrollAttributes(),
        );

        return DB::transaction(function () use ($existing, $attrs, $agg, $createdBy) {
            if ($existing !== null) {
                $existing->update($attrs);
                $record = $existing->fresh();
            } else {
                $record = PayrollRecord::query()->create($attrs);
            }

            $this->syncAdvanceAdjustments($record, $agg['adjustments'], $createdBy);

            return $record->fresh(['adjustments']);
        });
    }

    /**
     * @param  list<array{type: string, amount: float}>  $advances
     */
    private function syncAdvanceAdjustments(PayrollRecord $record, array $advances, int $createdBy): void
    {
        $record->adjustments()
            ->where('type', PayrollAdjustment::TYPE_ADVANCE)
            ->delete();

        foreach ($advances as $row) {
            if (($row['type'] ?? '') !== PayrollAdjustment::TYPE_ADVANCE) {
                continue;
            }
            PayrollAdjustment::query()->create([
                'payroll_record_id' => $record->id,
                'type' => PayrollAdjustment::TYPE_ADVANCE,
                'amount' => $row['amount'],
                'note' => null,
                'created_by' => $createdBy,
            ]);
        }
    }

    public function validateMonthCoverage(User $user, int $month, int $year): void
    {
        $gap = $this->coverageGap($user, $month, $year);

        if ($gap !== null) {
            throw new MonthCoverageException(
                $user->id,
                $month,
                $year,
                $gap['classified'],
                $gap['expected'],
            );
        }
    }

    /**
     * @return array{classified: int, expected: int}|null
     */
    public function coverageGap(User $user, int $month, int $year): ?array
    {
        $classified = $this->aggregator->classifiedDayCount($user, $month, $year);
        $expected = $this->aggregator->expectedDayCount($month, $year);

        if ($classified === $expected) {
            return null;
        }

        return ['classified' => $classified, 'expected' => $expected];
    }

    public function approve(PayrollRecord $record): PayrollRecord
    {
        if ($record->status !== PayrollRecord::STATUS_DRAFT) {
            return $record;
        }

        return DB::transaction(function () use ($record) {
            $record->update(['status' => PayrollRecord::STATUS_APPROVED]);
            $this->advances->applyDeductionsForPayroll($record);

            return $record->fresh();
        });
    }

    /**
     * @return list<int>
     */
    public function eligibleUserIds(?int $teamLeaderId = null): array
    {
        $query = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER, User::ROLE_ACCOUNTANT])
            ->orderBy('id');

        if ($teamLeaderId !== null) {
            $query->where(function ($q) use ($teamLeaderId) {
                $q->where('team_leader_id', $teamLeaderId)
                    ->orWhere('id', $teamLeaderId);
            });
        }

        return $query->pluck('id')->all();
    }

    /**
     * @param  array<string, mixed>  $agg
     */
    private function toInput(User $user, array $agg, int $extraDays = 0, float $extraPay = 0.0, float $overtimeMultiplier = 0.0): PayrollCalculationInput
    {
        $half = $user->online_half_day_rate;
        $full = $user->online_full_day_rate;
        $ot = $user->overtime_hourly_rate;

        return new PayrollCalculationInput(
            baseSalary: (float) $user->base_salary,
            currency: $user->currency ?: 'EGP',
            fullTimeDays: (int) $agg['full_time_days'],
            onlineHalfDays: (int) $agg['online_half_days'],
            onlineFullDays: (int) $agg['online_full_days'],
            officialHolidayWorkDays: (int) $agg['official_holiday_work_days'],
            excusedAbsenceDays: (int) $agg['excused_absence_days'],
            unexcusedAbsenceDays: (int) $agg['unexcused_absence_days'],
            hoursDeduction: (float) $agg['hours_deduction'],
            lateMinutesTotal: (int) $agg['late_minutes_total'],
            leavePermissionMinutes: (int) $agg['leave_permission_minutes'],
            latePermissionMinutes: (int) $agg['late_permission_minutes'],
            forcedPermissionPenaltyBase: (float) $agg['forced_permission_penalty_base'],
            fridayFullTimeDays: (int) $agg['friday_full_time_days'],
            fridayOnlineDays: (int) $agg['friday_online_days'],
            dayOffFullTimeDays: (int) $agg['day_off_full_time_days'],
            dayOffOnlineDays: (int) $agg['day_off_online_days'],
            overtimeHours: round(
                (float) $agg['overtime_hours'] + (float) $agg['extra_shift_hours'],
                2,
            ),
            extraShiftHours: 0.0,
            overtimeMultiplier: $overtimeMultiplier > 0 ? $overtimeMultiplier : null,
            extraDays: $extraDays,
            extraPay: $extraPay,
            onlineHalfDayRate: ((float) $half > 0) ? (float) $half : null,
            onlineFullDayRate: ((float) $full > 0) ? (float) $full : null,
            overtimeHourlyRate: ((float) $ot > 0) ? (float) $ot : null,
            adjustments: $agg['adjustments'],
        );
    }
}
