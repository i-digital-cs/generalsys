<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_logs_in_and_lands_on_admin_dashboard(): void
    {
        $manager = User::factory()->manager()->create();

        $this->post(route('login.store'), [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_team_leader_logs_in_and_lands_on_generic_dashboard(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->post(route('login.store'), [
            'email' => $leader->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_employee_logs_in_and_lands_on_self_profile(): void
    {
        $employee = User::factory()->employee()->create();

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertRedirect(route('employee.profile'));
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
}
