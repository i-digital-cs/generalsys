<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Auto clock-out removed — employees must clock out themselves.
 * Command kept so old Hostinger cron lines fail soft instead of missing-command errors.
 */
class AutoClockOutCommand extends Command
{
    protected $signature = 'attendance:auto-clock-out';

    protected $description = 'Deprecated: auto clock-out is disabled; employees must clock out manually.';

    public function handle(): int
    {
        $this->info('Auto clock-out is disabled. No open punches were closed.');

        return self::SUCCESS;
    }
}
