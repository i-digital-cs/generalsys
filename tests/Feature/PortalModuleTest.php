<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_portal_home(): void
    {
        $this->get(route('portal.home'))->assertRedirect(route('login'));
    }

    public function test_every_role_lands_on_portal_home_after_login(): void
    {
        $roles = [
            User::factory()->manager()->create(),
            User::factory()->assistant()->create(),
            User::factory()->teamLeader()->create(),
            User::factory()->accountant()->create(),
            User::factory()->employee()->create(),
        ];

        foreach ($roles as $user) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect(route('portal.home'));

            $this->post(route('logout'));
        }
    }

    public function test_home_shows_three_modules_without_hr_sidebar(): void
    {
        $employee = User::factory()->employee()->create();

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('portal.home'))
            ->assertOk()
            ->assertSee(__('portal.service'), false)
            ->assertSee(__('portal.product'), false)
            ->assertSee(__('portal.hr'), false)
            ->assertSee(__('portal.stats_title'), false)
            ->assertSee(__('portal.cards.present_today'), false)
            ->assertSee(__('portal.charts.attendance_trend'), false)
            ->assertSee(__('portal.clock.now'), false)
            ->assertSee('data-timezone="'.config('app.timezone').'"', false)
            ->assertSee('Africa/Cairo', false)
            ->assertDontSee(__('portal.settings'), false)
            ->assertDontSee(__('nav.clock'), false)
            ->assertDontSee(__('nav.payroll'), false);
    }

    public function test_home_hides_payroll_money_from_team_leader(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $html = $this->withSession(['locale' => 'en'])->actingAs($leader)
            ->get(route('portal.home'))
            ->assertOk()
            ->assertSee(__('portal.stats_scope_team'), false)
            ->getContent();

        $this->assertStringNotContainsString(__('payroll.net'), $html);
        $this->assertStringNotContainsString('ج.م', $html);
    }

    public function test_hr_tab_opens_existing_role_home(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($manager)
            ->get(route('portal.hr'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($leader)
            ->get(route('portal.hr'))
            ->assertRedirect(route('team-leader.dashboard'));

        $this->actingAs($employee)
            ->get(route('portal.hr'))
            ->assertRedirect(route('employee.dashboard'));

        $assistant = User::factory()->assistant()->create();
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($assistant)
            ->get(route('portal.hr'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($accountant)
            ->get(route('portal.hr'))
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_service_and_product_use_sidebar_like_hr(): void
    {
        $employee = User::factory()->employee()->create();

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('portal.service'))
            ->assertOk()
            ->assertSee(__('nav.valuation'), false)
            ->assertSee(__('nav.dashboard'), false)
            ->assertSee(__('valuation.stats.title'), false)
            ->assertDontSee(__('nav.clock'), false)
            ->assertDontSee(__('nav.payroll'), false);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('valuation.sheets.index', 'service'))
            ->assertOk()
            ->assertSee(__('valuation.errors.not_assigned'), false)
            ->assertSee(__('nav.valuation'), false)
            ->assertSee(route('valuation.sheets.index', 'service'), false);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('portal.product'))
            ->assertOk()
            ->assertSee(__('portal.product'), false)
            ->assertSee(__('nav.valuation'), false);

        $this->actingAs($employee)
            ->get('/production')
            ->assertRedirect('/product');
    }

    public function test_hr_screens_keep_sidebar_and_module_tabs(): void
    {
        $employee = User::factory()->employee()->create();

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('nav.clock'), false)
            ->assertSee(__('portal.service'), false)
            ->assertSee(__('portal.hr'), false);
    }

    public function test_manager_sees_settings_tab_outside_hr_sidebar(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.home'))
            ->assertOk()
            ->assertSee(__('portal.settings'), false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee(__('portal.settings'), false)
            ->assertDontSee(__('nav.clock'), false)
            ->assertDontSee(__('nav.employees'), false);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('portal.home'))
            ->assertOk()
            ->assertDontSee(__('portal.settings'), false);
    }
}
