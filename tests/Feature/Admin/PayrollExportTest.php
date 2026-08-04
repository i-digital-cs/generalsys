<?php

namespace Tests\Feature\Admin;

use App\Jobs\ExportPayrollExcelJob;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Payroll\PayrollExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gate 5.2 — PDF/Excel export authorized (MANAGER; employee own payslip only).
 */
class PayrollExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_download_payslip_pdf(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
            'net_pay' => 2808,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.export.pdf', $payroll))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_can_download_own_approved_pdf(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 2808,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary.export.pdf', $payroll))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_cannot_download_draft_pdf(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary.export.pdf', $payroll))
            ->assertForbidden();
    }

    public function test_team_leader_cannot_export_pdf_or_excel(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => User::factory()->employee()->create(['team_leader_id' => $leader->id])->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($leader);
        $this->get(route('admin.payroll.export.pdf', $payroll))->assertForbidden();
        $this->get(route('admin.payroll.export.xlsx', ['month' => 6, 'year' => 2026]))->assertForbidden();
    }

    public function test_manager_queues_excel_export_and_can_download(): void
    {
        Storage::fake('local');
        Queue::fake();

        $manager = User::factory()->manager()->create();
        PayrollRecord::factory()->create([
            'user_id' => User::factory()->employee()->create()->id,
            'month' => 6,
            'year' => 2026,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_APPROVED,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.export.xlsx', ['month' => 6, 'year' => 2026]))
            ->assertRedirect(route('admin.payroll.index', ['month' => 6, 'year' => 2026]));

        Queue::assertPushed(ExportPayrollExcelJob::class, function (ExportPayrollExcelJob $job) use ($manager) {
            return $job->month === 6
                && $job->year === 2026
                && $job->requestedBy === $manager->id;
        });

        // Run job against real local disk for download path
        Storage::disk('local')->makeDirectory('exports');
        $path = app(PayrollExportService::class)->buildMonthlyExcel(6, 2026);
        Cache::put(ExportPayrollExcelJob::cacheKey($manager->id, 6, 2026), $path, now()->addHour());

        $this->actingAs($manager)
            ->get(route('admin.payroll.export.download', ['month' => 6, 'year' => 2026]))
            ->assertOk();
    }
}
