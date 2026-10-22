<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\Attendance\ShiftWindowResolver;
use Illuminate\Console\Command;

/**
 * READ-ONLY detector for auto-closes that were fabricated when expectedEnd ≤ clock_in
 * (Reda-pattern). Does not modify rows — owner-approved repair is a separate step.
 */
class DetectFabricatedAutoClosesCommand extends Command
{
    protected $signature = 'attendance:detect-fabricated-auto-closes
                            {--user= : Limit to one user id}
                            {--from= : Y-m-d inclusive}
                            {--to= : Y-m-d inclusive}';

    protected $description = 'Detect auto-closed attendance rows with misaligned shift windows (read-only).';

    public function handle(ShiftWindowResolver $windows): int
    {
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

        $suspicious = 0;
        $checked = 0;

        $query->chunkById(100, function ($rows) use ($windows, &$suspicious, &$checked): void {
            foreach ($rows as $attendance) {
                if ($attendance->user === null || $attendance->clock_in === null) {
                    continue;
                }
                $checked++;
                $expectedEnd = $windows->expectedEndForAttendance($attendance);
                $misaligned = $expectedEnd === null || ! $expectedEnd->gt($attendance->clock_in);
                if (! $misaligned) {
                    continue;
                }

                $suspicious++;
                $this->line(sprintf(
                    'SUSPECT att=%d user=%d date=%s in=%s out=%s raw=%s expected_end=%s',
                    $attendance->id,
                    $attendance->user_id,
                    $attendance->date->toDateString(),
                    $attendance->clock_in->format('Y-m-d H:i:s'),
                    $attendance->clock_out?->format('Y-m-d H:i:s'),
                    $attendance->raw_actual_hours,
                    $expectedEnd?->format('Y-m-d H:i:s') ?? 'null',
                ));
            }
        });

        $this->info("Checked {$checked} auto-close rows; suspicious (misaligned window)={$suspicious}.");
        $this->warn('No rows were modified. Historical repair requires explicit owner approval.');

        return self::SUCCESS;
    }
}
