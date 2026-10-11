<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceSegment;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\ShiftConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Repair auto force-closes that collapsed to clock_in+1 minute when the profile
 * end was before clock-in (night work + daytime window). Restore clock_out to the
 * force-close moment (updated_at), capped at planned length from clock-in.
 */
class RepairStaleOneMinuteClosesCommand extends Command
{
    protected $signature = 'attendance:repair-stale-one-minute-closes
                            {--dry-run : Report without writing}
                            {--user= : Limit to one user id}
                            {--from= : Y-m-d inclusive}
                            {--to= : Y-m-d inclusive}';

    protected $description = 'Repair auto-closed punches that were wrongly closed after 1 minute.';

    public function handle(
        AttendanceTimeCalculator $calculator,
        AttendanceService $attendanceService,
        ShiftConfig $shifts,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');
        $from = $this->option('from');
        $to = $this->option('to');

        $query = Attendance::query()
            ->with(['user', 'segments'])
            ->where('is_auto_clock_out', true)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->orderBy('id');

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }
        if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $query->whereDate('date', '>=', $from);
        }
        if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $query->whereDate('date', '<=', $to);
        }

        $checked = 0;
        $fixed = 0;

        $query->chunkById(100, function ($rows) use (
            $calculator,
            $attendanceService,
            $shifts,
            $dryRun,
            &$checked,
            &$fixed,
        ): void {
            foreach ($rows as $attendance) {
                if ($attendance->user === null || $attendance->clock_in === null || $attendance->clock_out === null) {
                    continue;
                }

                $checked++;
                $spanMinutes = (int) $attendance->clock_in->diffInMinutes($attendance->clock_out, false);
                if ($spanMinutes < 0 || $spanMinutes > 2) {
                    continue;
                }
                if ((float) ($attendance->raw_actual_hours ?? 0) > 0.05) {
                    continue;
                }

                $plannedMinutes = max(1, (int) round($shifts->resolveForAttendance($attendance) * 60));
                $repairedOut = Carbon::parse($attendance->updated_at);
                $cap = $attendance->clock_in->copy()->addMinutes($plannedMinutes);
                if ($repairedOut->gt($cap)) {
                    $repairedOut = $cap;
                }
                if ($repairedOut->lte($attendance->clock_in)) {
                    continue;
                }

                $this->line(sprintf(
                    'user=%d date=%s %s → %s (was %s, raw=%s)',
                    $attendance->user_id,
                    $attendance->date->toDateString(),
                    $attendance->clock_in->format('Y-m-d H:i'),
                    $repairedOut->format('Y-m-d H:i'),
                    $attendance->clock_out->format('Y-m-d H:i'),
                    $attendance->raw_actual_hours,
                ));

                if ($dryRun) {
                    $fixed++;

                    continue;
                }

                $openSeg = $attendance->segments
                    ->sortByDesc('sequence')
                    ->first();
                if ($openSeg instanceof AttendanceSegment) {
                    $openSeg->update(['clock_out' => $repairedOut]);
                }

                $attendance->update([
                    'clock_out' => $repairedOut,
                    'is_auto_clock_out' => true,
                ]);

                $calculator->recalculate(
                    $attendance->fresh(['segments', 'user']),
                    $attendanceService,
                );
                $fixed++;
            }
        });

        $this->info(($dryRun ? 'Would repair' : 'Repaired')." {$fixed} of {$checked} auto-close rows.");

        return self::SUCCESS;
    }
}
