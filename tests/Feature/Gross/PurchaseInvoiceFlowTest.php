<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_stocks_in_and_duplicate_confirm_is_idempotent(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Fabric',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 50,
        ]);
        $supplier = GrossSupplier::query()->create([
            'name' => 'Cairo Textiles',
            'phone' => '01001110000',
        ]);

        $service = app(PurchaseInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
            'discount_amount' => 10,
        ], [[
            'item_id' => $item->id,
            'qty' => 4,
            'unit' => 'PIECE',
            'buy_price' => 40,
            'sale_price' => 80,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $this->assertStringStartsWith('MP-', $invoice->invoice_number);
        $this->assertSame(GrossPurchaseInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertEquals('150.00', (string) $invoice->total);

        $confirmed = $service->confirm($invoice, $manager);
        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $confirmed->status);

        $balance = GrossStockBalance::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame(4, $balance->qty_on_hand_pieces);

        $this->assertDatabaseHas('gross_stock_movements', [
            'item_id' => $item->id,
            'type' => GrossStockMovement::TYPE_PURCHASE_IN,
            'qty_pieces' => 4,
        ]);

        $this->assertSame('80.00', (string) $item->fresh()->default_sale_price);

        $again = $service->confirm($confirmed, $manager);
        $this->assertSame(4, GrossStockBalance::query()->where('item_id', $item->id)->value('qty_on_hand_pieces'));
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_PURCHASE_IN)->count());
        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $again->status);
    }

    public function test_http_create_confirm_and_sales_role_forbidden(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Thread',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 10,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.purchases.create'))
            ->assertOk()
            ->assertSee('Buy price', false)
            ->assertSee('Sale price', false)
            ->assertSee('Type item name or code', false)
            ->assertSee(route('gross.items.search', [], false), false)
            ->assertSee('data-gross-totals', false)
            ->assertDontSee('Thread', false);

        $this->actingAs($manager)
            ->post(route('gross.purchases.store'), [
                'supplier_name' => 'Walk-in mill',
                'supplier_phone' => '01550001111',
                'currency' => 'EGP',
                'discount_mode' => 'amount',
                'discount_amount' => 0,
                'lines' => [[
                    'item_id' => $item->id,
                    'qty' => 2,
                    'unit' => 'PIECE',
                    'buy_price' => 25,
                    'sale_price' => 40,
                    'discount_mode' => 'amount',
                    'discount_amount' => 0,
                ]],
            ])
            ->assertRedirect();

        $invoice = GrossPurchaseInvoice::query()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gross.purchases.confirm', $invoice))
            ->assertRedirect(route('gross.purchases.show', $invoice));

        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $invoice->fresh()->status);
        $this->assertSame(2, (int) GrossStockBalance::query()->where('item_id', $item->id)->value('qty_on_hand_pieces'));

        $sales = User::factory()->sales()->create();
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $this->actingAs($sales->fresh())
            ->get(route('gross.purchases.index'))
            ->assertForbidden();
    }

    public function test_cannot_cancel_confirmed_purchase(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Button',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $service = app(PurchaseInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'supplier_name' => 'X',
            'supplier_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'buy_price' => 5,
            'sale_price' => 8,
        ]]);
        $service->confirm($invoice, $manager);

        $this->actingAs($manager)
            ->from(route('gross.purchases.show', $invoice))
            ->post(route('gross.purchases.cancel', $invoice))
            ->assertForbidden();

        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $invoice->fresh()->status);
    }

    public function test_line_percent_discount_and_validation(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Zipper',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->post(route('gross.purchases.store'), [
                'supplier_name' => 'Mill',
                'supplier_phone' => '01002223333',
                'currency' => 'EGP',
                'discount_mode' => 'percent',
                'discount_percent' => 10,
                'lines' => [[
                    'item_id' => $item->id,
                    'qty' => 10,
                    'unit' => 'PIECE',
                    'buy_price' => 20,
                    'sale_price' => 30,
                    'discount_mode' => 'percent',
                    'discount_percent' => 10,
                ]],
            ])
            ->assertRedirect();

        $invoice = GrossPurchaseInvoice::query()->firstOrFail();
        $this->assertEquals('180.00', (string) $invoice->subtotal);
        $this->assertEquals('162.00', (string) $invoice->total);

        $this->actingAs($manager)
            ->post(route('gross.purchases.store'), [
                'supplier_name' => 'Bad',
                'supplier_phone' => '0100',
                'currency' => 'EGP',
                'lines' => [],
            ])
            ->assertSessionHasErrors('lines');
    }
}
