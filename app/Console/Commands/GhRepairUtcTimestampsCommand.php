<?php

namespace App\Console\Commands;

use App\Services\GeneralHouse\Orders\GhUtcTimestampRepairService;
use Illuminate\Console\Command;

class GhRepairUtcTimestampsCommand extends Command
{
    protected $signature = 'gh:repair-utc-timestamps {--apply : Persist corrections (default is dry-run)}';

    protected $description = 'Correct legacy GH TIMESTAMP wall-clock digits to canonical UTC (idempotent)';

    public function handle(GhUtcTimestampRepairService $repair): int
    {
        if ($repair->alreadyApplied()) {
            $this->info('Marker '.GhUtcTimestampRepairService::MARKER.' present — repair already applied.');

            return self::SUCCESS;
        }

        $result = $this->option('apply') ? $repair->apply() : $repair->preview();

        $this->line(json_encode([
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'orders_touched' => count($result['orders']),
            'events_touched' => count($result['events']),
            'assignments_touched' => count($result['assignments']),
            'attempts_touched' => count($result['attempts']),
            'detail' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
