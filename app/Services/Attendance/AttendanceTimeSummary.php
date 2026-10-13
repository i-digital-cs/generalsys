<?php

namespace App\Services\Attendance;

/**
 * System-recommended vs payroll-credited time with gaps and overtime (T-7.9 / R2 / R4).
 * Duration balance uses {@see AttendanceGracePolicy} (Task 1).
 */
readonly class AttendanceTimeSummary
{
    /**
     * @param  list<array{minutes: int, start: ?string, end: ?string, tl_recommendation: string, admin_decision: string}>  $gaps
     * @param  array<string, int|string>|null  $durationBalance  from AttendanceGracePolicy::durationBalance
     */
    public function __construct(
        public bool $open,
        public float $plannedHours,
        public float $systemRawHours,
        public float $systemCreditedHours,
        public int $systemLateMinutes,
        public int $systemEarlyMinutes,
        public float $appliedRawHours,
        public float $appliedCreditedHours,
        public int $appliedLateMinutes,
        public int $appliedEarlyMinutes,
        public bool $creditedOverridden,
        public bool $lateOverridden,
        public int $gapMinutesTotal,
        public array $gaps,
        public int $overtimeDeltaMinutes,
        public int $systemSuggestedOvertimeMinutes,
        public int $recommendedOvertimeMinutes,
        public int $approvedOvertimeMinutes,
        public float $creditedCapHours,
        public string $overtimeTlRecommendation,
        public string $overtimeAdminDecision,
        public ?string $windowStart = null,
        public ?string $windowEnd = null,
        public ?string $clockInAt = null,
        public ?string $clockOutAt = null,
        public ?string $clockInWindowPosition = null,
        public ?string $clockOutWindowPosition = null,
        public int $rawLateMinutes = 0,
        public int $rawEarlyMinutes = 0,
        public ?array $durationBalance = null,
    ) {}

    /** Policy-adjusted undertime (grace-exempt minutes removed). */
    public function undertimeMinutes(): int
    {
        return (int) ($this->durationBalance['counted_deficit_minutes'] ?? max(0, -$this->overtimeDeltaMinutes));
    }

    /** Raw physical surplus minutes (not auto-approved OT). */
    public function extraWorkedMinutes(): int
    {
        return (int) ($this->durationBalance['counted_surplus_minutes'] ?? max(0, $this->overtimeDeltaMinutes));
    }

    public function hasUndertime(): bool
    {
        return $this->undertimeMinutes() > 0;
    }

    /** @return AttendanceGracePolicy::CLASS_* */
    public function hoursBalanceClassification(): string
    {
        return (string) ($this->durationBalance['classification']
            ?? ($this->overtimeDeltaMinutes > 0
                ? AttendanceGracePolicy::CLASS_SURPLUS
                : ($this->overtimeDeltaMinutes < 0
                    ? AttendanceGracePolicy::CLASS_DEFICIT
                    : AttendanceGracePolicy::CLASS_BALANCED)));
    }

    public function hasOverrides(): bool
    {
        return $this->creditedOverridden || $this->lateOverridden;
    }

    public function hasGaps(): bool
    {
        return $this->gapMinutesTotal > 0;
    }

    public function hasOvertimeActivity(): bool
    {
        return $this->overtimeDeltaMinutes !== 0
            || $this->recommendedOvertimeMinutes > 0
            || $this->overtimeTlRecommendation !== 'NONE';
    }

    public function needsTlOvertimeReview(): bool
    {
        if ($this->open) {
            return false;
        }

        // First pass: system detected overtime and TL has not acted.
        if ($this->overtimeTlRecommendation === 'NONE' && $this->systemSuggestedOvertimeMinutes > 0) {
            return true;
        }

        // Two-way: Admin rejected → TL may recommend again.
        return $this->overtimeTlRecommendation === 'RECOMMEND'
            && $this->overtimeAdminDecision === 'REJECTED';
    }

    public function needsAdminOvertimeReview(): bool
    {
        return $this->overtimeTlRecommendation === 'RECOMMEND'
            && $this->overtimeAdminDecision === 'PENDING';
    }

    public function isOvertimeDecided(): bool
    {
        if ($this->overtimeTlRecommendation === 'NO_OVERTIME') {
            return true;
        }

        // Admin reject is not final — TL can reopen.
        return $this->overtimeTlRecommendation === 'RECOMMEND'
            && $this->overtimeAdminDecision === 'APPROVED';
    }

    public function creditedDiffersFromSystem(): bool
    {
        return abs($this->appliedCreditedHours - $this->systemCreditedHours) >= 0.01;
    }

    public function lateDiffersFromSystem(): bool
    {
        return $this->appliedLateMinutes !== $this->systemLateMinutes;
    }
}
