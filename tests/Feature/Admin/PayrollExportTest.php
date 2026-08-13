<?php

namespace Tests\Feature\Admin;

use App\Jobs\ExportPayrollExcelJob;
use App\Models\PayrollAdjustment;
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

    public function test_manager_can_download_full_review_pdf(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
            'month' => 8,
            'year' => 2026,
            'net_pay' => 2808,
            'full_time_days' => 20,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.export.review.pdf', $payroll))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        app()->setLocale('en');
        $html = view('exports.payroll-review-pdf', [
            'payroll' => $payroll->load(['user', 'adjustments']),
            'scores' => [
                'performance_score' => 90,
                'behavior_score' => 90,
                'productivity_score' => 90,
                'interest_return_score' => 90,
                'attendance_score' => 90,
                'total_score' => 90,
            ],
            'evaluationText' => 'Good month',
            'yearAverage' => 88.5,
            'evaluationSaved' => true,
            'attendanceSummary' => [
                'vacation_days' => 1,
                'official_holiday_days' => 0,
                'excused_absence_days' => 0,
                'unexcused_absence_days' => 0,
            ],
            'hoursMonth' => [
                'totals' => ['planned' => 160, 'actual' => 150, 'pct' => 93.75],
                'days' => [
                    [
                        'date' => '2026-08-03',
                        'planned_hours' => 8,
                        'actual_hours' => 6,
                        'planned_intervals' => [['label' => 'Full time']],
                        'actual_intervals' => [],
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString(__('payroll.tabs.pay'), $html);
        $this->assertStringContainsString(__('payroll.tabs.edit'), $html);
        $this->assertStringContainsString(__('payroll.tabs.attendance'), $html);
        $this->assertStringContainsString(__('payroll.tabs.evaluation'), $html);
        $this->assertStringContainsString(__('payroll.tabs.hours'), $html);
        $this->assertStringContainsString('Good month', $html);
        $this->assertStringContainsString('2026-08-03', $html);
    }

    public function test_team_leader_cannot_export_review_pdf(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => User::factory()->employee()->create(['team_leader_id' => $leader->id])->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($leader)
            ->get(route('admin.payroll.export.review.pdf', $payroll))
            ->assertForbidden();
    }

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

    public function test_arabic_payslip_pdf_html_uses_shaped_glyphs(): void
    {
        app()->setLocale('ar');

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'name_ar' => 'رضوى أحمد',
            'name_en' => 'Radwa',
        ]);
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
        ]);

        $html = view('exports.payslip-pdf', ['payroll' => $payroll->load('user')])->render();

        $this->assertStringNotContainsString('كشف الراتب', $html);
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $html);
        $this->assertStringNotContainsString('الراتب الأساسي', $html);
        $this->assertStringContainsString("font-family: 'Tajawal'", $html);
        $this->assertStringContainsString('text-align: right', $html);
        $this->assertStringContainsString('sig-box', $html);
        $this->assertStringContainsString('sig-blank', $html);
        // RTL table: amount cell before label cell
        $this->assertMatchesRegularExpression('/<td class="amount">[\s\d.,]+<\/td>\s*<th>/u', $html);

        $this->actingAs($manager)
            ->get(route('admin.payroll.export.pdf', $payroll))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_payslip_pdf_lists_manual_deduction_lines(): void
    {
        app()->setLocale('ar');

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
            'advance_deductions' => 3000,
            'total_deductions' => 3000,
        ]);

        PayrollAdjustment::factory()->create([
            'payroll_record_id' => $payroll->id,
            'type' => PayrollAdjustment::TYPE_OTHER_DEDUCTION,
            'amount' => 1000,
            'created_by' => $manager->id,
        ]);
        PayrollAdjustment::factory()->create([
            'payroll_record_id' => $payroll->id,
            'type' => PayrollAdjustment::TYPE_ADVANCE,
            'amount' => 2000,
            'created_by' => $manager->id,
        ]);

        $html = view('exports.payslip-pdf', ['payroll' => $payroll->load(['user', 'adjustments'])])->render();

        $this->assertStringContainsString('1,000.00', $html);
        $this->assertStringContainsString('2,000.00', $html);
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $html);
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
