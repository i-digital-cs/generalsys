<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGrossPages;
use Tests\TestCase;

class SalesHrAccessTest extends TestCase
{
    use AssignsGrossPages;
    use RefreshDatabase;

    public function test_sales_user_can_open_hr_dashboard_and_gross_when_product_assigned(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)
            ->get(route('portal.hr'))
            ->assertRedirect(route('employee.dashboard'));

        $this->actingAs($sales)
            ->get(route('employee.dashboard'))
            ->assertOk();

        $this->actingAs($sales)
            ->get(route('gross.sales.index'))
            ->assertForbidden();

        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);
        // T-15.6: Product module alone is not enough — Mohra page checklist required.
        $this->assignGrossPages($sales);

        $this->actingAs($sales->fresh())
            ->get(route('portal.product'))
            ->assertOk();

        $this->actingAs($sales->fresh())
            ->get(route('gross.sales.index'))
            ->assertOk();

        $this->actingAs($sales->fresh())
            ->get(route('gross.treasury.index'))
            ->assertForbidden();
    }

    public function test_sales_user_can_open_and_submit_daily_report(): void
    {
        app()->setLocale('en');

        $sales = User::factory()->sales()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'language_preference' => 'en',
        ]);

        $this->actingAs($sales)
            ->get(route('employee.report'))
            ->assertOk()
            ->assertSee(__('report.title'), false);

        $service = app(AttendanceService::class);
        $service->clockIn($sales, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($sales, Carbon::parse('2026-08-03 18:00:00'));

        $this->actingAs($sales)
            ->from(route('employee.report', ['date' => '2026-08-03']))
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Visited customers and closed two sales.',
            ])
            ->assertRedirect(route('employee.report', ['date' => '2026-08-03']))
            ->assertSessionHas('success');
    }

    public function test_sales_user_can_open_salary_and_advances(): void
    {
        app()->setLocale('en');

        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();

        PayrollRecord::factory()->create([
            'user_id' => $sales->id,
            'month' => 8,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 3000,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($sales)
            ->get(route('employee.salary'))
            ->assertOk()
            ->assertSee(__('salary.title'), false)
            ->assertSee('3,000', false);

        $this->actingAs($sales)
            ->get(route('employee.advances'))
            ->assertOk()
            ->assertSee(__('advances.my_title'), false);

        $this->actingAs($sales)
            ->get(route('employee.evaluation'))
            ->assertOk()
            ->assertSee(__('evaluation.me.title'), false);
    }
}
