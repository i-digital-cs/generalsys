<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExportPayrollExcelJob;
use App\Jobs\GeneratePayrollJob;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 — job retry/timeout aligned with Hostinger schedule worker.
 */
class PayrollJobHostingerConfigTest extends TestCase
{
    public function test_generate_payroll_job_matches_schedule_worker(): void
    {
        $job = new GeneratePayrollJob(10, 2026, 1, [1, 2]);

        $this->assertSame(1, $job->tries);
        $this->assertSame(50, $job->timeout);
    }

    public function test_export_payroll_excel_job_matches_schedule_worker(): void
    {
        $job = new ExportPayrollExcelJob(10, 2026, 1);

        $this->assertSame(1, $job->tries);
        $this->assertSame(50, $job->timeout);
    }
}
