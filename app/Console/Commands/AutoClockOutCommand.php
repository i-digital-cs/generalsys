<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Scheduled bulk auto clock-out removed — employees must clock out themselves.
 *
 * Kept so legacy Hostinger cron lines (`attendance:auto-clock-out`) fail soft
 * instead of missing-command errors. Not registered in `routes/console.php`.
 *
 * Distinct from AttendanceService::closeStaleOpenBeforeShiftStart, which still
 * force-closes stale prior-day open punches and may set is_auto_clock_out.
 */
class AutoClockOutCommand extends Command
{
    protected $signature = 'attendance:auto-clock-out';

    protected $description = 'Deprecated: scheduled auto clock-out is disabled; employees must clock out manually.';

    public function handle(): int
    {
        $this->info('Scheduled auto clock-out is disabled. No open punches were closed.');

        return self::SUCCESS;
    }
}
