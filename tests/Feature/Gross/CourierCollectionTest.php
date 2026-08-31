<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_collect_fee_then_deliver_and_block_deliver_while_pending(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);
        $shipping = GrossShippingCompany::query()->create([
            'name' => 'Bosta',
            'status' => 'ACTIVE',
        ]);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $shipping->id,
            'currency' => 'EGP',
            'shipping_fee' => 25,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]);
        $service->confirm($invoice, $manager);
        $invoice = $invoice->fresh();

        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_PENDING, $invoice->shipping_fee_status);

        $this->actingAs($manager)
            ->post(route('gross.sales.deliver', $invoice))
            ->assertForbidden();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.collections'))
            ->assertOk()
            ->assertSee($invoice->invoice_number, false)
            ->assertSee('Collect fee', false);

        $this->actingAs($manager)
            ->post(route('gross.sales.collect-fee', $invoice))
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_COLLECTED, $invoice->shipping_fee_status);
        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $invoice->status);

        $again = $service->collectShippingFee($invoice, $manager);
        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_COLLECTED, $again->shipping_fee_status);

        $this->actingAs($manager)
            ->post(route('gross.sales.deliver', $invoice))
            ->assertRedirect(route('gross.sales.show', $invoice));

        $invoice = $invoice->fresh();
        $this->assertSame(GrossSalesInvoice::STATUS_DELIVERED, $invoice->status);

        $balance = GrossStockBalance::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame(4, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $alloc = $invoice->lines->first()->allocation;
        $this->assertSame(1, $alloc->qty_delivered_pieces);
        $this->assertSame(0, $alloc->qty_with_courier_pieces);
    }

    public function test_zero_fee_can_deliver_without_collect(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Hat',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 2, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Sara',
            'customer_phone' => '0111',
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]);
        $service->confirm($invoice, $manager);

        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_NONE, $invoice->fresh()->shipping_fee_status);

        $this->actingAs($manager)
            ->post(route('gross.sales.deliver', $invoice->fresh()))
            ->assertRedirect();

        $this->assertSame(GrossSalesInvoice::STATUS_DELIVERED, $invoice->fresh()->status);

        $balance = GrossStockBalance::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame(1, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
    }

    public function test_deliver_after_partial_return_clears_remaining_with_courier(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Scarf',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $allocations = app(AllocationService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Nadia',
            'customer_phone' => '0144',
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 30,
        ]]);
        $service->confirm($invoice, $manager);
        $alloc = $invoice->fresh()->lines->first()->allocation;
        $allocations->partialReturn($alloc, 3, $manager);

        $service->markDelivered($invoice->fresh(), $manager);

        $balance = GrossStockBalance::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame(3, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);

        $alloc = $alloc->fresh();
        $this->assertSame(7, $alloc->qty_delivered_pieces);
        $this->assertSame(3, $alloc->qty_returned_pieces);
        $this->assertSame(0, $alloc->qty_with_courier_pieces);
    }

    public function test_mark_delivered_is_idempotent_for_stock(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Belt',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 1, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Karim',
            'customer_phone' => '0155',
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
        $service->confirm($invoice, $manager);
        $service->markDelivered($invoice->fresh(), $manager);
        $service->markDelivered($invoice->fresh(), $manager);

        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_DELIVERED)->count());
        $this->assertSame(0, GrossStockBalance::query()->where('item_id', $item->id)->value('qty_with_courier_pieces'));
    }

    public function test_mark_delivered_repairs_legacy_with_courier_on_already_delivered_invoice(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Legacy',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 1, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Legacy',
            'customer_phone' => '0166',
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
        $service->confirm($invoice, $manager);

        $invoice->update([
            'status' => GrossSalesInvoice::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->assertSame(1, GrossStockBalance::query()->where('item_id', $item->id)->value('qty_with_courier_pieces'));

        $service->markDelivered($invoice->fresh(), $manager);

        $this->assertSame(0, GrossStockBalance::query()->where('item_id', $item->id)->value('qty_with_courier_pieces'));
        $alloc = $invoice->fresh()->lines->first()->allocation;
        $this->assertSame(1, $alloc->qty_delivered_pieces);
    }

    public function test_sales_can_collect_unassigned_employee_cannot(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Bag',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 2, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Omar',
            'customer_phone' => '0122',
            'currency' => 'EGP',
            'shipping_fee' => 10,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
        $service->confirm($invoice, $manager);

        $sales = User::factory()->sales()->create();
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $this->actingAs($sales->fresh())
            ->post(route('gross.sales.collect-fee', $invoice))
            ->assertRedirect();

        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_COLLECTED, $invoice->fresh()->shipping_fee_status);

        $employee = User::factory()->employee()->create();
        $pending = $service->createDraft($manager, [
            'customer_name' => 'Lina',
            'customer_phone' => '0133',
            'currency' => 'EGP',
            'shipping_fee' => 8,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
        $service->confirm($pending, $manager);

        $this->actingAs($employee)
            ->post(route('gross.sales.collect-fee', $pending))
            ->assertForbidden();
    }
}
