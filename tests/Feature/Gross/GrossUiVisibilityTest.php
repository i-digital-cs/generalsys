<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrossUiVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_show_and_statement_expose_pay_action(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Pay Me Supplier',
            'phone' => '01005554444',
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Record payment', false)
            ->assertSee('supplier_id='.$supplier->id, false)
            ->assertSee('purpose=SUPPLIER', false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.suppliers.statement', $supplier))
            ->assertOk()
            ->assertSee('Record payment', false)
            ->assertSee('supplier_id='.$supplier->id, false);
    }

    public function test_item_show_and_stock_link_to_movements(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Visible Item',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.items.show', $item))
            ->assertOk()
            ->assertSee('Stock movements', false)
            ->assertSee('item_id='.$item->id, false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.stock.index'))
            ->assertOk()
            ->assertSee(route('gross.stock.movements', ['item_id' => $item->id], false), false);
    }

    public function test_purchase_and_sales_show_cross_links_and_movement_reference(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create(['name' => 'Link Supplier', 'phone' => '01006667777']);
        $customer = GrossCustomer::query()->create(['name' => 'Link Customer', 'phone' => '01008889999']);
        $shipping = GrossShippingCompany::query()->create([
            'name' => 'Link Courier',
            'phone' => '01001112222',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Link Cloth',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $purchases = app(PurchaseInvoiceService::class);
        $purchase = $purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 5,
            'unit' => 'PIECE',
            'buy_price' => 10,
            'sale_price' => 20,
        ]]);
        $purchases->confirm($purchase, $manager);
        $purchase = $purchase->fresh(['lines']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(route('gross.suppliers.show', $supplier, false), false)
            ->assertSee(route('gross.items.show', $item, false), false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.purchases.index'))
            ->assertOk()
            ->assertSee(route('gross.suppliers.show', $supplier, false), false);

        $sales = app(SalesInvoiceService::class);
        $sale = $sales->createDraft($manager, [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'shipping_company_id' => $shipping->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 20,
        ]]);
        $sales->confirm($sale, $manager);
        $sale = $sale->fresh();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.show', $sale))
            ->assertOk()
            ->assertSee(route('gross.customers.show', $customer, false), false)
            ->assertSee(route('gross.shipping.show', $shipping, false), false)
            ->assertSee(route('gross.items.show', $item, false), false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.stock.movements', ['item_id' => $item->id]))
            ->assertOk()
            ->assertSee($purchase->invoice_number, false)
            ->assertSee($sale->invoice_number, false)
            ->assertSee(route('gross.items.show', $item, false), false);
    }
}
