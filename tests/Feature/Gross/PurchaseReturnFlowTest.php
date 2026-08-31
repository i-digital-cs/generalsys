<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossPurchaseReturn;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_return_decreases_stock_and_cannot_over_return(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Bolt',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $purchases = app(PurchaseInvoiceService::class);
        $returns = app(PurchaseReturnService::class);

        $invoice = $purchases->createDraft($manager, [
            'supplier_name' => 'Mill',
            'supplier_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'buy_price' => 5,
            'sale_price' => 9,
        ]]);
        $purchases->confirm($invoice, $manager);
        $lineId = $invoice->fresh()->lines->first()->id;

        $ret = $returns->create($manager, $invoice->fresh(), [
            ['purchase_invoice_line_id' => $lineId, 'qty' => 3],
        ], 'Damaged');

        $this->assertStringStartsWith('MR-', $ret->return_number);
        $this->assertSame(7, (int) GrossStockBalance::query()->where('item_id', $item->id)->value('qty_on_hand_pieces'));
        $this->assertSame(3, (int) $invoice->fresh()->lines->first()->qty_returned_pieces);
        $this->assertDatabaseHas('gross_stock_movements', [
            'item_id' => $item->id,
            'type' => GrossStockMovement::TYPE_PURCHASE_RETURN,
            'qty_pieces' => 3,
        ]);
        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $invoice->fresh()->status);

        $this->expectException(GrossException::class);
        $returns->create($manager, $invoice->fresh(), [
            ['purchase_invoice_line_id' => $lineId, 'qty' => 8],
        ]);
    }

    public function test_http_return_and_show_history(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Needle',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $purchases = app(PurchaseInvoiceService::class);
        $invoice = $purchases->createDraft($manager, [
            'supplier_name' => 'Mill',
            'supplier_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 5,
            'unit' => 'PIECE',
            'buy_price' => 2,
            'sale_price' => 4,
        ]]);
        $purchases->confirm($invoice, $manager);
        $lineId = $invoice->fresh()->lines->first()->id;

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.purchases.show', $invoice))
            ->assertOk()
            ->assertSee('Purchase return', false)
            ->assertSee('Remaining', false);

        $this->actingAs($manager)
            ->post(route('gross.purchases.return', $invoice), [
                'notes' => 'Wrong color',
                'lines' => [[
                    'purchase_invoice_line_id' => $lineId,
                    'qty' => 2,
                ]],
            ])
            ->assertRedirect(route('gross.purchases.show', $invoice))
            ->assertSessionHas('success');

        $this->assertSame(1, GrossPurchaseReturn::query()->count());
        $this->assertSame(3, (int) GrossStockBalance::query()->where('item_id', $item->id)->value('qty_on_hand_pieces'));

        $html = $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.purchases.show', $invoice))
            ->assertOk()
            ->assertSee('Wrong color', false)
            ->assertSee('Return history', false)
            ->getContent();
        $this->assertStringContainsString('MR-', $html);

        $this->actingAs($manager)
            ->post(route('gross.purchases.return', $invoice), [
                'lines' => [[
                    'purchase_invoice_line_id' => $lineId,
                    'qty' => 99,
                ]],
            ])
            ->assertSessionHasErrors('return');
    }

    public function test_cannot_return_draft(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Pin',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $invoice = app(PurchaseInvoiceService::class)->createDraft($manager, [
            'supplier_name' => 'Mill',
            'supplier_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'buy_price' => 1,
            'sale_price' => 2,
        ]]);
        $lineId = $invoice->lines->first()->id;

        $this->actingAs($manager)
            ->post(route('gross.purchases.return', $invoice), [
                'lines' => [[
                    'purchase_invoice_line_id' => $lineId,
                    'qty' => 1,
                ]],
            ])
            ->assertForbidden();
    }
}
