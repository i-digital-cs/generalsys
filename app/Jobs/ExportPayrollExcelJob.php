<?php

namespace App\Jobs;

use App\Services\Payroll\PayrollExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Chunked monthly payroll Excel build (T-5.2 / Hostinger ≤30s).
 */
class ExportPayrollExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Match Hostinger schedule: `queue:work --tries=1 --max-time=50`. */
    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(
        public readonly int $month,
        public readonly int $year,
        public readonly int $requestedBy,
    ) {}

    public function handle(PayrollExportService $exports): void
    {
        $path = $exports->buildMonthlyExcel($this->month, $this->year);

        Cache::put(
            $this->cacheKey($this->requestedBy, $this->month, $this->year),
            $path,
            now()->addHours(2),
        );
    }

    public static function cacheKey(int $userId, int $month, int $year): string
    {
        return sprintf('payroll-export:%d:%04d-%02d', $userId, $year, $month);
    }
}
