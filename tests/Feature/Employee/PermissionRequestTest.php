<?php

namespace Tests\Feature\Employee;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-2.7 — employee leave/late permission create + list (My Requests).
 */
class PermissionRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'language_preference' => 'en',
        ]);
    }

    public function test_employee_can_open_requests_screen(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee(__('permissions.title'), false)
            ->assertSee(__('permissions.new'), false);
    }

    public function test_employee_can_submit_leave_permission(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LEAVE,
                'request_date' => '2026-08-05',
                'from_time' => '14:00',
                'to_time' => '16:00',
                'reason' => 'Doctor appointment',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'permissions']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', [
            'user_id' => $this->employee->id,
            'type' => Permission::TYPE_LEAVE,
            'status' => Permission::STATUS_PENDING,
            'team_leader_id' => $this->leader->id,
            'reason' => 'Doctor appointment',
        ]);

        $row = Permission::query()->where('user_id', $this->employee->id)->first();
        $this->assertSame('2026-08-05', $row->request_date->toDateString());
        $this->assertSame('14:00', $row->from_time);
        $this->assertSame('16:00', $row->to_time);
    }

    public function test_employee_can_submit_late_permission(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LATE,
                'request_date' => '2026-08-06',
                'from_time' => '10:00',
                'to_time' => '10:45',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'user_id' => $this->employee->id,
            'type' => Permission::TYPE_LATE,
            'status' => Permission::STATUS_PENDING,
        ]);
    }

    public function test_list_shows_status_badge_and_escapes_reason(): void
    {
        app()->setLocale('en');

        Permission::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '15:00',
            'reason' => '<script>alert(1)</script>Need leave',
            'status' => Permission::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee(__('permissions.statuses.PENDING'), false)
            ->assertSee(__('permissions.types.LEAVE_PERMISSION'), false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Need leave', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_validation_rejects_to_before_from(): void
    {
        $this->actingAs($this->employee)
            ->from(route('employee.requests'))
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LEAVE,
                'request_date' => '2026-08-05',
                'from_time' => '16:00',
                'to_time' => '14:00',
            ])
            ->assertRedirect(route('employee.requests'))
            ->assertSessionHasErrors('to_time');
    }

    public function test_employee_without_team_leader_cannot_submit(): void
    {
        $orphan = User::factory()->employee()->create([
            'team_leader_id' => null,
        ]);

        $this->actingAs($orphan)
            ->from(route('employee.requests'))
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LEAVE,
                'request_date' => '2026-08-05',
                'from_time' => '14:00',
                'to_time' => '16:00',
            ])
            ->assertRedirect(route('employee.requests'))
            ->assertSessionHasErrors('type');
    }

    public function test_team_leader_can_access_own_requests(): void
    {
        $this->actingAs($this->leader)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee(__('permissions.title'), false);
    }

    public function test_vacations_tab_shows_form_and_rest_pool(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->get(route('employee.requests', ['tab' => 'vacations']))
            ->assertOk()
            ->assertSee(__('vacations.new'), false)
            ->assertSee(__('vacations.rest_pool_title'), false);
    }
}
