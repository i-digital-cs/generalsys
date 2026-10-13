<?php

namespace Tests\Feature\Gross;

use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AssignsGrossPages;
use Tests\TestCase;

class GrossFoundationTest extends TestCase
{
    use AssignsGrossPages;
    use RefreshDatabase;

    public function test_gross_tables_and_sales_role_exist(): void
    {
        foreach ([
            'gross_categories',
            'gross_items',
            'gross_stock_balances',
            'gross_stock_movements',
            'gross_shipping_companies',
            'gross_customers',
            'gross_suppliers',
            'gross_purchase_invoice_sequences',
            'gross_purchase_invoices',
            'gross_purchase_invoice_lines',
            'gross_purchase_return_sequences',
            'gross_purchase_returns',
            'gross_purchase_return_lines',
            'gross_invoice_sequences',
            'gross_sales_invoices',
            'gross_sales_invoice_lines',
            'gross_shipment_allocations',
            'gross_treasury_accounts',
            'gross_treasury_voucher_sequences',
            'gross_treasury_vouchers',
            'gross_expense_types',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }

        $this->assertTrue(Schema::hasColumn('users', 'can_confirm_gross_loss'));

        $sales = User::factory()->sales()->create();
        $this->assertSame(User::ROLE_SALES, $sales->role);
        $this->assertTrue($sales->isSales());
        $this->assertFalse($sales->canConfirmGrossLoss());
        $this->assertTrue($sales->mayUseClock());
    }

    public function test_product_menu_includes_gross_routes(): void
    {
        $routes = array_values(array_filter(array_map(
            fn (array $item) => $item['route'] ?? null,
            config('module_menu.product'),
        )));
        $this->assertContains('gross.items.index', $routes);
        $this->assertContains('gross.stock.index', $routes);
        $this->assertContains('gross.stock.movements', $routes);
        $this->assertContains('gross.customers.index', $routes);
        $this->assertContains('gross.suppliers.index', $routes);
        $this->assertContains('gross.purchases.index', $routes);
        $this->assertContains('gross.shipping.index', $routes);
        $this->assertContains('gross.sales.index', $routes);
        $this->assertContains('gross.sales.collections', $routes);
        $this->assertContains('gross.treasury.index', $routes);
        $this->assertContains('gross.expense-types.index', $routes);
    }

    public function test_unassigned_user_gets_403_on_gross_items(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('gross.items.index'))
            ->assertForbidden();
    }

    public function test_assigned_employee_and_manager_can_open_gross_items(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        ValuationAssignment::query()->create([
            'user_id' => $employee->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);
        // T-15.6: Product module + page checklist (items). Manager always allowed.
        $this->assignGrossPages($employee, ['items']);

        $this->actingAs($manager)
            ->get(route('gross.items.index'))
            ->assertOk();

        $this->actingAs($employee->fresh())
            ->get(route('gross.items.index'))
            ->assertOk();
    }

    public function test_collections_sidebar_highlights_courier_tab_not_sales(): void
    {
        $manager = User::factory()->manager()->create();

        $collections = $this->withSession(['locale' => 'ar'])->actingAs($manager)
            ->get(route('gross.sales.collections'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($collections, 'nav-link px-3 active'),
            'Collections page should highlight exactly one sidebar item.');

        $sales = $this->actingAs($manager)
            ->get(route('gross.sales.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($sales, 'nav-link px-3 active'),
            'Sales index should highlight exactly one sidebar item.');
    }
}
