<?php

namespace Tests\Feature;

use Tests\TestCase;

class MenuConfigTest extends TestCase
{
    public function test_menu_is_keyed_by_the_three_roles(): void
    {
        $menu = config('menu');

        $this->assertSame(['MANAGER', 'TEAM_LEADER', 'EMPLOYEE'], array_keys($menu));

        foreach ($menu as $role => $items) {
            $this->assertNotEmpty($items, "Menu for {$role} is empty");

            foreach ($items as $item) {
                $this->assertArrayHasKey('key', $item);
                $this->assertArrayHasKey('route', $item);
                $this->assertArrayHasKey('icon', $item);
            }
        }
    }

    public function test_payroll_entries_exist_only_for_manager(): void
    {
        foreach (['TEAM_LEADER', 'EMPLOYEE'] as $role) {
            foreach (config("menu.{$role}") as $item) {
                $this->assertStringNotContainsString('payroll', $item['route']);
                $this->assertStringNotContainsString('payroll', $item['key']);
            }
        }

        $managerRoutes = array_column(config('menu.MANAGER'), 'route');
        $this->assertContains('admin.payroll.index', $managerRoutes);
    }

    public function test_every_menu_key_is_translated_in_ar_and_en(): void
    {
        foreach (config('menu') as $items) {
            foreach ($items as $item) {
                foreach (['ar', 'en'] as $locale) {
                    $this->assertNotSame(
                        $item['key'],
                        app('translator')->get($item['key'], [], $locale),
                        "Missing {$locale} translation for {$item['key']}"
                    );
                }
            }
        }
    }
}
