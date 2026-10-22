<?php

namespace App\Services\Attendance;

/**
 * Canonical attendance fact for one session row (SSOT read model).
 * Minutes are authoritative; hours are presentation helpers with one rounding policy.
 */
readonly class AttendanceCalculationResult
{
    public const POLICY_VERSION = 'attendance-grace-v1';

    public function __construct(
        public int $attendanceId,
        public int $userId,
        public string $businessDate,
        public string $shiftType,
        public string $checkoutProvenance,
        public AttendanceTimeSummary $summary,
        public string $policyVersion,
        public bool $gracePolicyActive,
        public ?string $status,
        public int $exactLateMinutes,
        public int $exactEarlyMinutes,
    ) {}

    public static function hoursFromMinutes(int $minutes): float
    {
        return round(max(0, $minutes) / 60, 2);
    }

    public static function minutesFromHours(float $hours): int
    {
        return (int) round(max(0, $hours) * 60);
    }

    public function plannedMinutes(): int
    {
        return self::minutesFromHours($this->summary->plannedHours);
    }

    public function actualWorkedMinutes(): int
    {
        return self::minutesFromHours($this->summary->systemRawHours);
    }

    public function creditedMinutes(): int
    {
        return self::minutesFromHours($this->summary->appliedCreditedHours);
    }

    /** Payroll / cross-screen counted late (override + date-aware grace). */
    public function countedLateMinutes(): int
    {
        return max(0, $this->exactLateMinutes);
    }

    /** Payroll / cross-screen counted early (date-aware grace). */
    public function countedEarlyMinutes(): int
    {
        return max(0, $this->exactEarlyMinutes);
    }

    public function rawLateMinutes(): int
    {
        return max(0, $this->summary->rawLateMinutes);
    }

    public function rawEarlyMinutes(): int
    {
        return max(0, $this->summary->rawEarlyMinutes);
    }

    public function deficitMinutes(): int
    {
        return $this->summary->undertimeMinutes();
    }

    public function surplusMinutes(): int
    {
        return $this->summary->extraWorkedMinutes();
    }

    public function gapMinutes(): int
    {
        return max(0, $this->summary->gapMinutesTotal);
    }

    public function approvedOvertimeMinutes(): int
    {
        return max(0, $this->summary->approvedOvertimeMinutes);
    }

    public function isOpen(): bool
    {
        return $this->summary->open || $this->checkoutProvenance === CheckoutProvenance::OPEN;
    }

    public function checkoutVerified(): bool
    {
        return CheckoutProvenance::isVerifiedEmployeeCheckout($this->checkoutProvenance);
    }

    /** Recorded raw duration exists and checkout is employee-verified. */
    public function durationVerified(): bool
    {
        return ! $this->isOpen() && CheckoutProvenance::durationIsVerified($this->checkoutProvenance);
    }

    /** Stored/span hours (may be fabricated for AUTO_STALE). Never silently zeroed. */
    public function recordedWorkedHours(): float
    {
        return round(max(0, (float) $this->summary->systemRawHours), 2);
    }

    /**
     * Verified worked hours for display. Null when checkout missing or not employee-verified
     * (do not treat null as 0.00h).
     */
    public function verifiedWorkedHours(): ?float
    {
        if (! $this->durationVerified()) {
            return null;
        }

        return $this->recordedWorkedHours();
    }

    /**
     * Credited / applied hours on the row (payroll-facing snapshot when settled elsewhere).
     * Still shown for AUTO_STALE but must be labeled recorded/credited, not verified worked.
     */
    public function creditedDisplayHours(): float
    {
        return round(max(0, (float) $this->summary->appliedCreditedHours), 2);
    }

    /**
     * Why credited hours exist (not the same as verified work).
     *
     * @return 'MANUAL_OVERRIDE'|'OT_APPROVED'|'POLICY_CAP'|'NONE'
     */
    public function creditedProvenance(): string
    {
        if ($this->isOpen() || $this->creditedDisplayHours() <= 0) {
            return 'NONE';
        }
        if ($this->summary->creditedOverridden) {
            return 'MANUAL_OVERRIDE';
        }
        if ($this->approvedOvertimeMinutes() > 0) {
            return 'OT_APPROVED';
        }

        // Default recalculate path: min(raw, planned) persisted as actual_hours.
        return 'POLICY_CAP';
    }

    /** Credited hours need human review when based on unverified checkout (no silent zeroing). */
    public function creditedReviewRequired(): bool
    {
        return $this->creditedProvenance() !== 'NONE'
            && ! $this->durationVerified();
    }

    public function overtimeEligibleMinutes(): int
    {
        return max(0, $this->summary->systemSuggestedOvertimeMinutes);
    }

    /**
     * Planned window does not own this punch (e.g. night punch vs 00:00–12:00 day profile).
     * Do not invent a replacement shift — flag for manual review.
     */
    public function shiftReviewRequired(): bool
    {
        if ($this->summary->clockInWindowPosition === 'after_window') {
            return true;
        }

        if ($this->checkoutProvenance === CheckoutProvenance::AUTO_STALE
            && $this->summary->clockInWindowPosition !== 'inside_window'
            && $this->summary->clockInWindowPosition !== null) {
            return true;
        }

        return false;
    }

    /**
     * Late minutes against the resolved planned window (SSOT exact*).
     * When {@see shiftReviewRequired()}, UI must label as window-relative / review — not as trusted lateness.
     */
    public function lateAgainstPlannedWindowMinutes(): int
    {
        return $this->countedLateMinutes();
    }

    public function lateDisplayTrustworthy(): bool
    {
        return ! $this->shiftReviewRequired() && ! $this->isOpen();
    }

    public function needsReview(): bool
    {
        return $this->reviewReasonCodes() !== [];
    }

    /**
     * Structured review reason codes for presentation (not calculation).
     *
     * @return list<string>
     */
    public function reviewReasonCodes(): array
    {
        $codes = [];

        if ($this->shiftReviewRequired()) {
            $codes[] = 'SHIFT_WINDOW_MISMATCH';
        }
        if ($this->checkoutProvenance === CheckoutProvenance::AUTO_STALE) {
            $codes[] = 'AUTO_CHECKOUT';
        }
        if (! $this->isOpen() && ! $this->durationVerified()) {
            $codes[] = 'UNVERIFIED_RECORDED_HOURS';
        }
        if ($this->creditedReviewRequired()) {
            $codes[] = 'CREDITED_POLICY_UNVERIFIED';
        }
        if (! $this->lateDisplayTrustworthy() && $this->lateAgainstPlannedWindowMinutes() > 0) {
            $codes[] = 'LATE_DEPENDS_ON_UNCONFIRMED_SHIFT';
        }

        return array_values(array_unique($codes));
    }
}
