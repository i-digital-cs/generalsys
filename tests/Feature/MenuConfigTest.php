<?php

namespace Tests\Feature;

use Tests\TestCase;

class MenuConfigTest extends TestCase
{
    public function test_menu_is_keyed_by_the_staff_roles(): void
    {
        $menu = config('menu');

        $this->assertSame(
            ['MANAGER', 'ASSISTANT', 'ACCOUNTANT', 'TEAM_LEADER', 'EMPLOYEE', 'DRIVER', 'SALES'],
            array_keys($menu),
        );

        foreach ($menu as $role => $items) {
            $this->assertNotEmpty($items, "Menu for {$role} is empty");

            foreach ($items as $item) {
                if (($item['type'] ?? '') === 'heading') {
                    $this->assertArrayHasKey('key', $item);

                    continue;
                }
                $this->assertArrayHasKey('key', $item);
                $this->assertArrayHasKey('route', $item);
                $this->assertArrayHasKey('icon', $item);
            }
        }
    }

    public function test_payroll_entries_exist_only_for_manager_and_accountant(): void
    {
        foreach (config('menu.TEAM_LEADER') as $item) {
            $this->assertStringNotContainsString('payroll', $item['route']);
            $this->assertStringNotContainsString('payroll', $item['key']);
            $this->assertStringNotContainsString('advances', $item['route']);
        }

        foreach (config('menu.EMPLOYEE') as $item) {
            $this->assertStringNotContainsString('payroll', $item['route']);
            $this->assertStringNotContainsString('payroll', $item['key']);
            $this->assertStringNotContainsString('admin.advances', $item['route']);
        }

        foreach (config('menu.DRIVER') as $item) {
            $this->assertStringNotContainsString('payroll', $item['route']);
            $this->assertStringNotContainsString('salary', $item['route']);
            $this->assertStringNotContainsString('advances', $item['route']);
        }

        $this->assertSame(['employee.clock', 'employee.report', 'employee.media'], array_column(config('menu.DRIVER'), 'route'));

        foreach (config('menu.ASSISTANT') as $item) {
            $this->assertStringNotContainsString('payroll', $item['route']);
            $this->assertStringNotContainsString('payroll', $item['key']);
            $this->assertStringNotContainsString('admin.advances', $item['route']);
            $this->assertStringNotContainsString('admin.settings', $item['route']);
        }

        $managerRoutes = array_column(config('menu.MANAGER'), 'route');
        $this->assertContains('admin.payroll.index', $managerRoutes);
        $this->assertContains('admin.advances.index', $managerRoutes);
        $this->assertContains('team-leader.shifts', $managerRoutes);
        $this->assertNotContains('admin.settings', $managerRoutes);
        $this->assertNotContains('employee.clock', $managerRoutes);
        $this->assertNotContains('employee.report', $managerRoutes);
        $this->assertNotContains('employee.requests', $managerRoutes);

        $accountantRoutes = array_column(config('menu.ACCOUNTANT'), 'route');
        $this->assertContains('admin.payroll.index', $accountantRoutes);
        $this->assertContains('admin.advances.index', $accountantRoutes);
        $this->assertContains('employee.clock', $accountantRoutes);
        $this->assertNotContains('admin.employees.index', $accountantRoutes);

        $assistantRoutes = array_column(config('menu.ASSISTANT'), 'route');
        $this->assertContains('admin.employees.index', $assistantRoutes);
        $this->assertContains('team-leader.shifts', $assistantRoutes);
        $this->assertContains('team-leader.rest-days', $assistantRoutes);
        $this->assertContains('team-leader.reports', $assistantRoutes);
        $this->assertContains('team-leader.attendance', $assistantRoutes);
        $this->assertContains('team-leader.approvals', $assistantRoutes);
        $this->assertContains('team-leader.attendance', $managerRoutes);
        $this->assertContains('team-leader.attendance', array_column(config('menu.TEAM_LEADER'), 'route'));
        $this->assertNotContains('admin.payroll.index', $assistantRoutes);
        $this->assertNotContains('team-leader.evaluations', $assistantRoutes);
    }

    public function test_every_menu_key_is_translated_in_ar_and_en(): void
    {
        $allItems = config('menu');
        foreach (config('module_menu') as $items) {
            $allItems[] = $items;
        }

        foreach ($allItems as $items) {
            foreach ($items as $item) {
                if (($item['type'] ?? '') === 'heading') {
                    continue;
                }
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

    public function test_service_and_product_menus_point_at_valuation(): void
    {
        $linkRoutes = static function (array $items): array {
            return array_values(array_filter(array_map(
                fn (array $item) => $item['route'] ?? null,
                $items,
            )));
        };

        $serviceRoutes = $linkRoutes(config('module_menu.service'));
        $productRoutes = $linkRoutes(config('module_menu.product'));

        $this->assertContains('portal.service', $serviceRoutes);
        $this->assertContains('valuation.sheets.index', $serviceRoutes);
        $this->assertContains('portal.product', $productRoutes);
        $this->assertContains('valuation.sheets.index', $productRoutes);
        $this->assertContains('gross.items.index', $productRoutes);
        $this->assertContains('gross.categories.index', $productRoutes);
        $this->assertContains('gross.stock.index', $productRoutes);
        $this->assertContains('gross.customers.index', $productRoutes);
        $this->assertContains('gross.shipping.index', $productRoutes);
        $this->assertContains('gross.sales.index', $productRoutes);
        $this->assertContains('gross.sales.stats', $productRoutes);
    }
}
