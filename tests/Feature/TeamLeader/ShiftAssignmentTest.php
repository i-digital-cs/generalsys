<?php

namespace Tests\Feature\TeamLeader;

use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3.7 / T-2.14 — TL shift assignment create/cancel; team scope; employee 403.
 */
class ShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $otherLeader = User::factory()->teamLeader()->create();
        $this->otherEmployee = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
        ]);
    }

    public function test_team_leader_can_open_shifts_screen(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->get(route('team-leader.shifts'))
            ->assertOk()
            ->assertSee(__('shifts.title'), false)
            ->assertSee(__('shifts.my_team'), false)
            ->assertSee($this->employee->name_en, false)
            ->assertDontSee($this->otherEmployee->name_en, false);
    }

    public function test_team_leader_can_edit_scheduled_assignment(): void
    {
        $assignment = ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $this->leader->id,
            'date' => '2026-08-03',
            'expected_hours' => 8,
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.shifts.edit', $assignment))
            ->assertOk()
            ->assertSee(__('shifts.edit_title'), false);

        $this->actingAs($this->leader)
            ->put(route('team-leader.shifts.update', $assignment), [
                'user_id' => $this->employee->id,
                'date' => '2026-08-03',
                'shift_type' => ShiftAssignment::TYPE_ONLINE_HALF,
                'expected_start_time' => '10:00',
                'expected_end_time' => '14:00',
                'expected_hours' => 4,
                'multiplier' => 1,
                'notes' => 'Switched to online',
            ])
            ->assertRedirect(route('team-leader.shifts', ['date' => '2026-08-03']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shift_assignments', [
            'id' => $assignment->id,
            'shift_type' => ShiftAssignment::TYPE_ONLINE_HALF,
            'expected_hours' => '4.00',
            'notes' => 'Switched to online',
        ]);
    }

    public function test_team_leader_cannot_edit_other_team_assignment(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();
        $assignment = ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->otherEmployee->id,
            'assigned_by_tl_id' => $otherLeader->id,
            'date' => '2026-08-03',
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.shifts.edit', $assignment))
            ->assertForbidden();
    }

    public function test_team_leader_can_assign_full_time(): void
    {
        $this->actingAs($this->leader)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->employee->id,
                'date' => '2026-08-03',
                'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
                'expected_start_time' => '10:00',
                'expected_end_time' => '18:00',
                'expected_hours' => 8,
                'multiplier' => 1,
            ])
            ->assertRedirect(route('team-leader.shifts', ['date' => '2026-08-03']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $this->leader->id,
            'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
            'expected_hours' => '8.00',
        ]);
    }

    public function test_team_leader_can_assign_overtime(): void
    {
        $this->actingAs($this->leader)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->employee->id,
                'date' => now()->toDateString(),
                'shift_type' => ShiftAssignment::TYPE_OVERTIME,
                'expected_start_time' => '18:00',
                'expected_end_time' => '22:00',
                'expected_hours' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $this->employee->id,
            'shift_type' => ShiftAssignment::TYPE_OVERTIME,
            'multiplier' => '1.50',
        ]);
    }

    public function test_duplicate_scheduled_assignment_is_rejected(): void
    {
        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $this->leader->id,
            'date' => '2026-08-03',
        ]);

        $this->actingAs($this->leader)
            ->from(route('team-leader.shifts', ['date' => '2026-08-03']))
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->employee->id,
                'date' => '2026-08-03',
                'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
                'expected_start_time' => '10:00',
                'expected_end_time' => '18:00',
                'expected_hours' => 8,
            ])
            ->assertRedirect(route('team-leader.shifts', ['date' => '2026-08-03']))
            ->assertSessionHasErrors('shift_type');
    }

    public function test_team_leader_cannot_assign_other_team_employee(): void
    {
        $this->actingAs($this->leader)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->otherEmployee->id,
                'date' => '2026-08-03',
                'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
                'expected_start_time' => '10:00',
                'expected_end_time' => '18:00',
                'expected_hours' => 8,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, ShiftAssignment::count());
    }

    public function test_team_leader_can_cancel_scheduled_assignment(): void
    {
        $assignment = ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $this->leader->id,
            'date' => '2026-08-03',
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.shifts.cancel', $assignment), ['date' => '2026-08-03'])
            ->assertRedirect(route('team-leader.shifts', ['date' => '2026-08-03']))
            ->assertSessionHas('success');

        $this->assertSame(
            ShiftAssignment::STATUS_CANCELLED,
            $assignment->fresh()->status,
        );
    }

    public function test_team_leader_cannot_cancel_other_team_assignment(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();
        $assignment = ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->otherEmployee->id,
            'assigned_by_tl_id' => $otherLeader->id,
            'date' => '2026-08-03',
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.shifts.cancel', $assignment))
            ->assertForbidden();
    }

    public function test_employee_cannot_access_shift_assignment(): void
    {
        $this->actingAs($this->employee)
            ->get(route('team-leader.shifts'))
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->employee->id,
                'date' => '2026-08-03',
                'shift_type' => ShiftAssignment::TYPE_OVERTIME,
                'expected_start_time' => '18:00',
                'expected_end_time' => '22:00',
                'expected_hours' => 4,
            ])
            ->assertForbidden();
    }

    public function test_assigned_full_time_is_preferred_on_employee_clock(): void
    {
        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $this->leader->id,
            'date' => now()->toDateString(),
        ]);

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.assigned_today', [
                'shift' => __('clock.shifts.FULL_TIME'),
            ]), false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/option value="FULL_TIME"[^>]*selected/s',
            $html,
        );
    }

    public function test_manager_can_assign_any_employee(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->otherEmployee->id,
                'date' => '2026-08-03',
                'shift_type' => ShiftAssignment::TYPE_ONLINE_HALF,
                'expected_start_time' => '10:00',
                'expected_end_time' => '14:00',
                'expected_hours' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $this->otherEmployee->id,
            'assigned_by_tl_id' => $manager->id,
            'shift_type' => ShiftAssignment::TYPE_ONLINE_HALF,
        ]);
    }
}
