<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairGrossDeliveredStockCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_repairs_legacy_delivered_invoice_stock(): void
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

        $this->artisan('hrpro:repair-gross-delivered-stock')
            ->assertSuccessful()
            ->expectsOutputToContain($invoice->fresh()->invoice_number);

        $this->assertSame(0, GrossStockBalance::query()->where('item_id', $item->id)->value('qty_with_courier_pieces'));
        $alloc = $invoice->fresh()->lines->first()->allocation;
        $this->assertSame(1, $alloc->qty_delivered_pieces);
        $this->assertSame(0, $alloc->qty_with_courier_pieces);
    }

    public function test_dry_run_does_not_change_stock(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Dry',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 1, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $invoice = $service->createDraft($manager, [
            'customer_name' => 'Dry',
            'customer_phone' => '0177',
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
        $service->confirm($invoice, $manager);
        $invoice->update(['status' => GrossSalesInvoice::STATUS_DELIVERED, 'delivered_at' => now()]);

        $this->artisan('hrpro:repair-gross-delivered-stock', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(1, GrossStockBalance::query()->where('item_id', $item->id)->value('qty_with_courier_pieces'));
    }
}
