<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\User;
use App\Models\ValuationAssignment;
use App\Support\GeneralHouse\GhServiceMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GH-9.4: Service sidebar must not render unauthorized finance links for DRIVER.
 */
class Gh94DriverServiceMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_service_menu_hides_finance_and_keeps_workspace(): void
    {
        $driver = User::factory()->driver()->create();

        $routes = array_column(
            GhServiceMenu::filterForUser($driver, config('module_menu.service')),
            'route',
        );

        $this->assertContains('gh.driver.dashboard', $routes);
        $this->assertNotContains('gh.invoices.index', $routes);
        $this->assertNotContains('gh.payments.index', $routes);
        $this->assertNotContains('gh.dashboard', $routes);
        $this->assertNotContains('gh.reports.index', $routes);
        $this->assertNotContains('gh.reports.outstanding', $routes);
        $this->assertNotContains('gh.reports.collections', $routes);
        $this->assertNotContains('gh.orders.index', $routes);
        $this->assertNotContains('gh.orders.create', $routes);
    }

    public function test_manager_service_menu_keeps_finance_navigation(): void
    {
        $manager = User::factory()->manager()->create();

        $routes = array_column(
            GhServiceMenu::filterForUser($manager, config('module_menu.service')),
            'route',
        );

        $this->assertContains('gh.dashboard', $routes);
        $this->assertContains('gh.invoices.index', $routes);
        $this->assertContains('gh.payments.index', $routes);
        $this->assertContains('gh.reports.index', $routes);
        $this->assertContains('gh.reports.outstanding', $routes);
        $this->assertContains('gh.orders.index', $routes);
        $this->assertNotContains('gh.driver.dashboard', $routes);
    }

    public function test_driver_sidebar_html_omits_finance_links_in_arabic_and_english(): void
    {
        $driver = User::factory()->driver()->create();
        ValuationAssignment::query()->create([
            'user_id' => $driver->id,
            'module' => 'service',
        ]);

        foreach (['ar', 'en'] as $locale) {
            $html = $this->actingAs($driver)
                ->withSession(['locale' => $locale])
                ->get(route('gh.driver.dashboard'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString(route('gh.driver.dashboard'), $html);
            $this->assertStringNotContainsString(route('gh.invoices.index'), $html);
            $this->assertStringNotContainsString(route('gh.payments.index'), $html);
            $this->assertStringNotContainsString(route('gh.dashboard'), $html);
            $this->assertStringNotContainsString(route('gh.reports.index'), $html);
            $this->assertStringNotContainsString(route('gh.reports.outstanding'), $html);
            $this->assertStringNotContainsString(route('gh.reports.collections'), $html);
        }
    }

    public function test_manager_sidebar_still_shows_finance_links(): void
    {
        $manager = User::factory()->manager()->create();

        $html = $this->actingAs($manager)
            ->get(route('gh.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('gh.invoices.index'), $html);
        $this->assertStringContainsString(route('gh.payments.index'), $html);
        $this->assertStringContainsString(route('gh.reports.outstanding'), $html);
    }

    public function test_driver_direct_finance_routes_remain_forbidden(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)->get(route('gh.invoices.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.payments.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.dashboard'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.reports.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.reports.outstanding'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.reports.collections'))->assertForbidden();
    }

    public function test_driver_hr_menu_unchanged(): void
    {
        $this->assertSame(
            ['gh.driver.dashboard', 'employee.clock', 'employee.report', 'employee.media'],
            array_column(config('menu.DRIVER'), 'route'),
        );
    }
}
