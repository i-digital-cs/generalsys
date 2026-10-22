<?php

namespace App\Services\Attendance;

/**
 * Presentation-only mapping of SSOT review flags → localized tooltip copy.
 * Does not recalculate attendance business rules.
 */
final class AttendanceReviewPresentation
{
    /**
     * @param  list<string>  $reasonCodes
     * @return list<string>
     */
    public function reasonLabels(array $reasonCodes): array
    {
        $labels = [];
        foreach ($reasonCodes as $code) {
            $key = 'attendance.review.reasons.'.$code;
            if (__($key) !== $key) {
                $labels[] = __($key);
            }
        }

        return $labels;
    }

    /**
     * Single review tooltip for one session (dynamic placeholders from summary).
     */
    public function sessionTooltip(AttendanceCalculationResult $result): string
    {
        $codes = $result->reviewReasonCodes();
        if ($codes === []) {
            return '';
        }

        $lines = $this->reasonLabels($codes);

        if (in_array('SHIFT_WINDOW_MISMATCH', $codes, true)
            || in_array('AUTO_CHECKOUT', $codes, true)
            || in_array('CREDITED_POLICY_UNVERIFIED', $codes, true)) {
            array_unshift($lines, __('attendance.review.narrative.shift_auto_policy', [
                'window_start' => $result->summary->windowStart ?? '—',
                'window_end' => $result->summary->windowEnd ?? '—',
                'clock_in' => $result->summary->clockInAt ?? '—',
            ]));
        }

        return implode("\n", array_values(array_unique($lines)));
    }

    /**
     * Day-level review tooltip (union of session reasons + one narrative when applicable).
     *
     * @param  list<AttendanceCalculationResult>  $results
     */
    public function dayTooltip(array $results): string
    {
        $codes = [];
        $primary = null;
        foreach ($results as $result) {
            if (! $result instanceof AttendanceCalculationResult) {
                continue;
            }
            $codes = array_merge($codes, $result->reviewReasonCodes());
            $primary ??= $result;
        }
        $codes = array_values(array_unique($codes));
        if ($codes === [] || $primary === null) {
            return '';
        }

        return $this->sessionTooltip($primary);
    }

    /**
     * @param  list<AttendanceCalculationResult>  $results
     * @return list<string>
     */
    public function dayReasonCodes(array $results): array
    {
        $codes = [];
        foreach ($results as $result) {
            if ($result instanceof AttendanceCalculationResult) {
                $codes = array_merge($codes, $result->reviewReasonCodes());
            }
        }

        return array_values(array_unique($codes));
    }
}
