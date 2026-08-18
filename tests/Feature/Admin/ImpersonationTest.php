<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\SettingService;
use App\Services\Auth\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'name_ar' => 'المدير',
            'name_en' => 'Admin Manager',
        ]);
        $this->employee = User::factory()->employee()->create([
            'name_ar' => 'رضوى',
            'name_en' => 'Radwa',
        ]);
        $this->settings = app(SettingService::class);
    }

    public function test_login_as_button_hidden_when_setting_off(): void
    {
        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertDontSee(__('impersonation.login_as'), false);

        $this->actingAs($this->manager)
            ->from(route('admin.employees.index'))
            ->post(route('admin.employees.impersonate', $this->employee))
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($this->manager);
    }

    public function test_manager_impersonates_employee_and_returns(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee(__('impersonation.login_as'), false);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.impersonate', $this->employee))
            ->assertRedirect(route('employee.dashboard'))
            ->assertSessionHas('success')
            ->assertSessionHas(ImpersonationService::SESSION_KEY, $this->manager->id);

        $this->actingAs($this->employee)
            ->withSession([
                ImpersonationService::SESSION_KEY => $this->manager->id,
                'locale' => 'en',
            ])
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('impersonation.banner', ['name' => 'Radwa']), false)
            ->assertSee(__('impersonation.return'), false);

        $this->actingAs($this->employee)
            ->withSession([ImpersonationService::SESSION_KEY => $this->manager->id])
            ->post(route('impersonation.leave'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success')
            ->assertSessionMissing(ImpersonationService::SESSION_KEY);
    }

    public function test_manager_impersonates_team_leader(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.employees.impersonate', $leader))
            ->assertRedirect(route('team-leader.dashboard'))
            ->assertSessionHas(ImpersonationService::SESSION_KEY, $this->manager->id);
    }

    public function test_cannot_impersonate_self(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.impersonate', $this->manager))
            ->assertForbidden();
    }

    public function test_nested_impersonation_is_blocked(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');
        $otherManager = User::factory()->manager()->create();
        $other = User::factory()->employee()->create();

        $this->actingAs($otherManager)
            ->withSession([ImpersonationService::SESSION_KEY => $this->manager->id])
            ->from(route('admin.employees.index'))
            ->post(route('admin.employees.impersonate', $other))
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('error');
    }

    public function test_team_leader_assistant_and_employee_cannot_start(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');

        $leader = User::factory()->teamLeader()->create();
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($leader)
            ->post(route('admin.employees.impersonate', $this->employee))
            ->assertForbidden();

        $this->actingAs($assistant)
            ->post(route('admin.employees.impersonate', $this->employee))
            ->assertForbidden();

        $this->withSession(['locale' => 'en'])->actingAs($assistant)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertDontSee(__('impersonation.login_as'), false);

        $this->actingAs($this->employee)
            ->post(route('admin.employees.impersonate', $leader))
            ->assertForbidden();
    }

    public function test_leave_without_impersonation_keeps_session(): void
    {
        $this->actingAs($this->manager)
            ->from(route('admin.dashboard'))
            ->post(route('impersonation.leave'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($this->manager);
    }

    public function test_logout_while_impersonating_ends_session(): void
    {
        $this->actingAs($this->employee)
            ->withSession([ImpersonationService::SESSION_KEY => $this->manager->id])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
