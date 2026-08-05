<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

/**
 * Hostinger: runs via schedule:run — force-close forgotten punches after grace.
 */
class AutoClockOutCommand extends Command
{
    protected $signature = 'attendance:auto-clock-out';

    protected $description = 'Force clock-out open punches after expected end + grace (mark automatic)';

    public function handle(AttendanceService $attendance): int
    {
        $closed = $attendance->autoClockOutOverdue();

        $this->info("Auto clock-out closed {$closed} open punch(es).");

        return self::SUCCESS;
    }
}
