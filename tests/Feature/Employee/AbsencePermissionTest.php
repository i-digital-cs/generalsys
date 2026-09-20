<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\Permission;
use App\Models\RestDay;
use App\Models\User;
use App\Services\Payroll\PayrollAggregator;
use App\Support\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-15.1 — full-day ABSENCE_PERMISSION → EXCUSED_ABSENCE attendance / payroll count.
 */
class AbsencePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $leader;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->leader = User::factory()->teamLeader()->create([
            'base_salary' => 3000,
            'currency' => 'EGP',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'base_salary' => 3000,
            'currency' => 'EGP',
            'language_preference' => 'en',
        ]);
    }

    public function test_employee_can_submit_absence_without_times(): void
    {
        $this->withSession(['locale' => 'en'])->actingAs($this->employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee('Absence permission', false);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-22',
                'end_date' => '2026-09-22',
                'reason' => 'Family matter',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'permissions']))
            ->assertSessionHas('success');

        $row = Permission::query()->where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame(Permission::TYPE_ABSENCE, $row->type);
        $this->assertSame(Permission::STATUS_PENDING, $row->status);
        $this->assertSame('2026-09-22', $row->end_date->toDateString());
        $this->assertSame('00:00', $row->from_time);
        $this->assertSame('23:59', $row->to_time);
    }

    public function test_accept_period_marks_each_day_excused(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-20',
                'end_date' => '2026-09-22',
            ])
            ->assertRedirect();

        $permission = Permission::query()->firstOrFail();
        $this->assertSame(3, $permission->absenceDayCount());

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.approve', $permission))
            ->assertRedirect();

        foreach (['2026-09-20', '2026-09-21', '2026-09-22'] as $day) {
            $this->assertTrue(
                Attendance::query()
                    ->where('user_id', $this->employee->id)
                    ->whereDate('date', $day)
                    ->where('status', Attendance::STATUS_EXCUSED_ABSENCE)
                    ->exists(),
                "Missing excused attendance for {$day}"
            );
        }

        $agg = app(PayrollAggregator::class)->aggregate($this->employee, 9, 2026);
        $this->assertSame(3, $agg['excused_absence_days']);
    }

    public function test_tl_can_submit_absence_for_admin_accept(): void
    {
        $this->actingAs($this->leader)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-24',
                'end_date' => '2026-09-24',
            ])
            ->assertRedirect();

        $permission = Permission::query()->where('user_id', $this->leader->id)->firstOrFail();
        $this->assertNull($permission->team_leader_id);

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.permissions.approve', $permission))
            ->assertRedirect();

        $this->assertTrue(
            Attendance::query()
                ->where('user_id', $this->leader->id)
                ->whereDate('date', '2026-09-24')
                ->where('status', Attendance::STATUS_EXCUSED_ABSENCE)
                ->exists()
        );
    }

    public function test_force_after_reject_also_marks_excused(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-25',
                'end_date' => '2026-09-25',
            ]);

        $permission = Permission::query()->firstOrFail();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.reject', $permission))
            ->assertRedirect();

        $this->assertSame(0, Attendance::query()->count());

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.force', $permission->fresh()))
            ->assertRedirect();

        $this->assertSame(Permission::STATUS_FORCED, $permission->fresh()->status);
        $this->assertTrue(
            Attendance::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-09-25')
                ->where('status', Attendance::STATUS_EXCUSED_ABSENCE)
                ->exists()
        );
    }

    public function test_cannot_accept_when_day_already_worked(): void
    {
        Attendance::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-09-26',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_PRESENT,
            'clock_in' => '2026-09-26 10:00:00',
            'planned_hours' => 8,
            'actual_hours' => 8,
        ]);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-26',
                'end_date' => '2026-09-26',
            ]);

        $permission = Permission::query()->firstOrFail();

        $this->actingAs($this->leader)
            ->from(route('team-leader.approvals'))
            ->post(route('team-leader.approvals.permissions.approve', $permission))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame(Permission::STATUS_PENDING, $permission->fresh()->status);
    }

    public function test_arabic_label_on_requests_screen(): void
    {
        $this->withSession(['locale' => 'ar'])->actingAs($this->employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee('إذن غياب', false)
            ->assertSee('من تاريخ', false)
            ->assertSee('إلى تاريخ', false);
    }

    public function test_absence_period_fields_visible_when_type_selected(): void
    {
        $this->withSession(['locale' => 'en'])->actingAs($this->employee)
            ->get(route('employee.requests', ['tab' => 'permissions']))
            ->assertOk()
            ->assertSee('data-permission-period', false)
            ->assertSee('data-permission-date-start', false)
            ->assertSee('data-permission-date-end', false)
            ->assertSee(__('permissions.fields.from_date'), false)
            ->assertSee(__('permissions.fields.end_date'), false);

        $this->withSession(['locale' => 'en'])->actingAs($this->leader)
            ->get(route('employee.requests', ['tab' => 'permissions']))
            ->assertOk()
            ->assertSee(__('permissions.fields.from_date'), false)
            ->assertSee(__('permissions.fields.end_date'), false);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('employee.requests', ['tab' => 'permissions']))
            ->assertOk()
            ->assertSee(__('permissions.fields.from_date'), false)
            ->assertSee(__('permissions.fields.end_date'), false);
    }

    public function test_period_max_31_days_and_rejects_32(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-01',
                'end_date' => '2026-10-01', // 31 days
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-11-01',
                'end_date' => '2026-12-03', // 33 days
            ])
            ->assertSessionHasErrors();
    }

    public function test_overlapping_absence_blocked(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-10',
                'end_date' => '2026-09-12',
            ])
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-12',
                'end_date' => '2026-09-14',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_rest_day_in_period_is_skipped_not_excused(): void
    {
        RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-09-21',
            'created_by' => $this->leader->id,
        ]);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-20',
                'end_date' => '2026-09-22',
            ])
            ->assertRedirect();

        $permission = Permission::query()->firstOrFail();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.approve', $permission))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(
            Attendance::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-09-20')
                ->where('status', Attendance::STATUS_EXCUSED_ABSENCE)
                ->exists()
        );
        $this->assertFalse(
            Attendance::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-09-21')
                ->where('status', Attendance::STATUS_EXCUSED_ABSENCE)
                ->exists()
        );
        $this->assertTrue(
            Attendance::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-09-22')
                ->where('status', Attendance::STATUS_EXCUSED_ABSENCE)
                ->exists()
        );

        $agg = app(PayrollAggregator::class)->aggregate($this->employee, 9, 2026);
        $this->assertSame(2, $agg['excused_absence_days']);
    }

    public function test_mid_period_present_names_conflict_date(): void
    {
        Attendance::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-09-21',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_PRESENT,
            'clock_in' => '2026-09-21 10:00:00',
            'planned_hours' => 8,
            'actual_hours' => 8,
        ]);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-20',
                'end_date' => '2026-09-22',
            ]);

        $permission = Permission::query()->firstOrFail();

        $this->actingAs($this->leader)
            ->from(route('team-leader.approvals'))
            ->post(route('team-leader.approvals.permissions.approve', $permission))
            ->assertRedirect()
            ->assertSessionHasErrors('permission');

        $message = session('errors')->first('permission');
        $this->assertStringContainsString('2026-09-21', (string) $message);
        $this->assertSame(Permission::STATUS_PENDING, $permission->fresh()->status);
    }

    public function test_notification_body_includes_period(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-20',
                'end_date' => '2026-09-22',
            ])
            ->assertRedirect();

        $notification = $this->leader->notifications()->latest('id')->first();
        $this->assertNotNull($notification);
        $data = $notification->data;
        $this->assertSame('2026-09-20', $data['request_date']);
        $this->assertSame('2026-09-22', $data['end_date']);

        $body = NotificationMessage::body($data);
        $this->assertStringContainsString('2026-09-20', $body);
        $this->assertStringContainsString('2026-09-22', $body);
    }

    public function test_forced_absence_aggregator_uses_reject_base_not_doubled(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_ABSENCE,
                'request_date' => '2026-09-15',
                'end_date' => '2026-09-15',
            ]);

        $permission = Permission::query()->firstOrFail();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.reject', $permission))
            ->assertRedirect();

        $rejected = $permission->fresh();
        $rejectBase = (float) $rejected->penalty_amount;
        $this->assertGreaterThan(0, $rejectBase);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.force', $rejected))
            ->assertRedirect();

        $forced = $permission->fresh();
        $this->assertTrue($forced->is_double_penalty);
        $this->assertEqualsWithDelta($rejectBase * 2, (float) $forced->penalty_amount, 0.01);

        $agg = app(PayrollAggregator::class)->aggregate($this->employee, 9, 2026);
        $this->assertSame(1, $agg['excused_absence_days']);
        $this->assertEqualsWithDelta($rejectBase, $agg['forced_permission_penalty_base'], 0.01);
    }
}
