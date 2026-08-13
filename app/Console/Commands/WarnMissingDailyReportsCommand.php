<?php

namespace App\Console\Commands;

use App\Services\Attendance\MissingDailyReportService;
use Illuminate\Console\Command;

/**
 * Hostinger: runs via schedule:run — same-day evening (21:00) + next morning (08:00).
 */
class WarnMissingDailyReportsCommand extends Command
{
    protected $signature = 'reports:warn-missing {offset=0 : Days back from today (0 = today, 1 = yesterday)}';

    protected $description = 'Warn team leaders and managers about missing daily reports';

    public function handle(MissingDailyReportService $missing): int
    {
        $offset = max(0, (int) $this->argument('offset'));
        $date = now()->subDays($offset);
        $kind = $offset === 0
            ? MissingDailyReportService::KIND_TODAY
            : MissingDailyReportService::KIND_OVERDUE;

        $sent = $missing->notifyForDate($date, $kind);

        $this->info("Missing-report warnings ({$kind}) for {$date->toDateString()}: {$sent} notification(s) sent.");

        return self::SUCCESS;
    }
}
