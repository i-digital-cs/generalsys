<?php

namespace Tests\Feature\Gross;

use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GrossFoundationTest extends TestCase
{
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
            'gross_invoice_sequences',
            'gross_sales_invoices',
            'gross_sales_invoice_lines',
            'gross_shipment_allocations',
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
        $routes = array_column(config('module_menu.product'), 'route');
        $this->assertContains('gross.items.index', $routes);
        $this->assertContains('gross.stock.index', $routes);
        $this->assertContains('gross.stock.movements', $routes);
        $this->assertContains('gross.customers.index', $routes);
        $this->assertContains('gross.suppliers.index', $routes);
        $this->assertContains('gross.purchases.index', $routes);
        $this->assertContains('gross.shipping.index', $routes);
        $this->assertContains('gross.sales.index', $routes);
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

        $this->actingAs($manager)
            ->get(route('gross.items.index'))
            ->assertOk();

        $this->actingAs($employee->fresh())
            ->get(route('gross.items.index'))
            ->assertOk();
    }
}
