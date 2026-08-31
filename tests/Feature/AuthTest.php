<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_logs_in_and_lands_on_portal_home(): void
    {
        $manager = User::factory()->manager()->create();

        $this->post(route('login.store'), [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect(route('portal.home'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_team_leader_logs_in_and_lands_on_portal_home(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->post(route('login.store'), [
            'email' => $leader->email,
            'password' => 'password',
        ])->assertRedirect(route('portal.home'));
    }

    public function test_employee_logs_in_and_lands_on_portal_home(): void
    {
        $employee = User::factory()->employee()->create();

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertRedirect(route('portal.home'));
    }

    public function test_language_preference_is_applied_on_login(): void
    {
        $employee = User::factory()->employee()->create(['language_preference' => 'en']);

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertSessionHas('locale', 'en');
    }

    public function test_bad_credentials_are_rejected(): void
    {
        $employee = User::factory()->employee()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_user_logs_in_and_sees_inactive_notice(): void
    {
        $employee = User::factory()->employee()->inactive()->create();

        $this->withSession(['locale' => 'en'])->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertRedirect(route('account.inactive'));

        $this->assertAuthenticatedAs($employee);

        $this->get(route('account.inactive'))
            ->assertOk()
            ->assertSee(__('auth.inactive_title'), false)
            ->assertSee(__('auth.inactive_message'), false);
    }

    public function test_inactive_user_cannot_access_portal_home(): void
    {
        $employee = User::factory()->employee()->inactive()->create();

        $this->actingAs($employee)
            ->get(route('portal.home'))
            ->assertRedirect(route('account.inactive'));
    }

    public function test_inactive_user_can_log_out(): void
    {
        $employee = User::factory()->employee()->inactive()->create();

        $this->actingAs($employee)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_deactivated_while_logged_in_is_redirected_on_next_request(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('portal.home'))
            ->assertOk();

        $employee->update(['status' => User::STATUS_INACTIVE]);

        $this->actingAs($employee->fresh())
            ->get(route('employee.dashboard'))
            ->assertRedirect(route('account.inactive'));
    }
}
