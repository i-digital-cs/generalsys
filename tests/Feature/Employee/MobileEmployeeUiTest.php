<?php

namespace Tests\Feature\Employee;

use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-5.4 — mobile-friendly markup on clock / requests / salary.
 */
class MobileEmployeeUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_has_full_width_touch_cta(): void
    {
        $this->freezeClockToMorningShift();
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee('clock-cta', false)
            ->assertSee('btn-lg w-100', false)
            ->assertSee('employee-mobile-stack', false);
    }

    public function test_requests_tabs_are_scrollable_on_narrow_screens(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee('nav-tabs', false)
            ->assertSee('flex-nowrap', false);
    }

    public function test_salary_list_has_mobile_cards_and_desktop_table(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 2808,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary'))
            ->assertOk()
            ->assertSee('d-md-none employee-mobile-stack', false)
            ->assertSee('d-none d-md-block', false)
            ->assertSee('table-responsive', false)
            ->assertSee('btn-outline-primary w-100', false);
    }

    public function test_layout_declares_mobile_viewport(): void
    {
        $this->freezeClockToMorningShift();
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee('name="viewport"', false)
            ->assertSee('width=device-width', false);
    }
}
