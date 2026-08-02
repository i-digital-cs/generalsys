<?php

namespace Tests\Feature\Admin;

use App\Models\RestDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestDayTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->employee = User::factory()->employee()->create();
    }

    public function test_admin_marks_a_rest_day(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.rest-days.store', $this->employee), ['date' => '2026-08-14'])
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $restDay = $this->employee->restDays()->firstOrFail();
        $this->assertSame('2026-08-14', $restDay->date->toDateString());
        $this->assertSame($this->manager->id, $restDay->created_by);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        $this->employee->restDays()->create(['date' => '2026-08-14', 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.rest-days.store', $this->employee), ['date' => '2026-08-14'])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, $this->employee->restDays()->count());
    }

    public function test_admin_removes_a_rest_day(): void
    {
        $restDay = $this->employee->restDays()->create(['date' => '2026-08-14', 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->delete(route('admin.employees.rest-days.destroy', [$this->employee, $restDay]))
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $this->assertSame(0, $this->employee->restDays()->count());
    }

    public function test_cannot_delete_another_employees_rest_day_via_url(): void
    {
        $other = User::factory()->employee()->create();
        $restDay = $other->restDays()->create(['date' => '2026-08-14', 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->delete(route('admin.employees.rest-days.destroy', [$this->employee, $restDay]))
            ->assertNotFound();

        $this->assertSame(1, RestDay::count());
    }

    public function test_team_leader_gets_403_on_rest_days(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $restDay = $this->employee->restDays()->create(['date' => '2026-08-14', 'created_by' => $this->manager->id]);

        $this->actingAs($leader)
            ->post(route('admin.employees.rest-days.store', $this->employee), ['date' => '2026-08-15'])
            ->assertForbidden();

        $this->actingAs($leader)
            ->delete(route('admin.employees.rest-days.destroy', [$this->employee, $restDay]))
            ->assertForbidden();
    }

    public function test_employee_gets_403_on_rest_days(): void
    {
        $restDay = $this->employee->restDays()->create(['date' => '2026-08-14', 'created_by' => $this->manager->id]);

        $this->actingAs($this->employee)
            ->post(route('admin.employees.rest-days.store', $this->employee), ['date' => '2026-08-15'])
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->delete(route('admin.employees.rest-days.destroy', [$this->employee, $restDay]))
            ->assertForbidden();
    }

    public function test_edit_page_lists_rest_days(): void
    {
        $this->employee->restDays()->create(['date' => '2026-08-14', 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->get(route('admin.employees.edit', $this->employee))
            ->assertOk()
            ->assertSee('2026-08-14');
    }
}
