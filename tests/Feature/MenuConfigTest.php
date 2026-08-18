<?php

namespace Tests\Feature;

use Tests\TestCase;

class MenuConfigTest extends TestCase
{
    public function test_menu_is_keyed_by_the_five_roles(): void
    {
        $menu = config('menu');

        $this->assertSame(['MANAGER', 'ASSISTANT', 'ACCOUNTANT', 'TEAM_LEADER', 'EMPLOYEE'], array_keys($menu));

        foreach ($menu as $role => $items) {
            $this->assertNotEmpty($items, "Menu for {$role} is empty");

            foreach ($items as $item) {
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

        foreach (config('menu.ASSISTANT') as $item) {
            $this->assertStringNotContainsString('payroll', $item['route']);
            $this->assertStringNotContainsString('payroll', $item['key']);
            $this->assertStringNotContainsString('admin.advances', $item['route']);
            $this->assertStringNotContainsString('admin.settings', $item['route']);
            $this->assertStringNotContainsString('approvals', $item['route']);
        }

        $managerRoutes = array_column(config('menu.MANAGER'), 'route');
        $this->assertContains('admin.payroll.index', $managerRoutes);
        $this->assertContains('admin.advances.index', $managerRoutes);
        $this->assertContains('team-leader.shifts', $managerRoutes);
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
        $this->assertContains('team-leader.attendance', $managerRoutes);
        $this->assertContains('team-leader.attendance', array_column(config('menu.TEAM_LEADER'), 'route'));
        $this->assertNotContains('admin.payroll.index', $assistantRoutes);
        $this->assertNotContains('team-leader.approvals', $assistantRoutes);
        $this->assertNotContains('team-leader.evaluations', $assistantRoutes);
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
