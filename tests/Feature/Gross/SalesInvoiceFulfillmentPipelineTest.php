<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesInvoiceFulfillmentPipelineTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    private User $manager;

    private GrossItem $item;

    private SalesInvoiceService $invoices;

    private int $shippingId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->invoices = app(SalesInvoiceService::class);

        $this->item = app(ItemService::class)->create([
            'name' => 'Dress',
            'pieces_per_carton' => 5,
            'reorder_level' => 2,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($this->item, 20, GrossStockMovement::UNIT_PIECE, $this->manager);

        app(ShippingCompanyService::class)->create([
            'name' => 'Bosta',
            'status' => 'ACTIVE',
        ]);
        $this->shippingId = (int) GrossShippingCompany::query()->value('id');
    }

    public function test_confirm_starts_preparing_without_stock_out(): void
    {
        $invoice = $this->draft(5);
        $confirmed = $this->invoices->confirm($invoice, $this->manager);

        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_PREPARING, $confirmed->fulfillment_status);
        $this->assertNull($confirmed->lines->first()->allocation);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(20, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $this->assertSame(0, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());
    }

    public function test_ready_requires_packing_image(): void
    {
        $confirmed = $this->invoices->confirm($this->draft(3), $this->manager);

        try {
            $this->invoices->markReadyInWarehouse($confirmed);
            $this->fail('Expected packing image required');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.packing_image_required', $e->getMessage());
        }
    }

    public function test_pipeline_to_courier_moves_stock_once(): void
    {
        Storage::fake('local');
        $confirmed = $this->invoices->confirm($this->draft(6), $this->manager);
        $withImage = $this->invoices->storePackingImage(
            $confirmed,
            UploadedFile::fake()->image('pack.jpg', 120, 120),
            $this->manager
        );
        $this->assertTrue($withImage->hasPackingImage());

        $ready = $this->invoices->markReadyInWarehouse($withImage);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_READY_WAREHOUSE, $ready->fulfillment_status);

        $shipped = $this->invoices->handToCourier($ready, $this->manager);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_WITH_COURIER, $shipped->fulfillment_status);
        $this->assertNotNull($shipped->lines->first()->allocation);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(14, $balance->qty_on_hand_pieces);
        $this->assertSame(6, $balance->qty_with_courier_pieces);
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());

        $again = $this->invoices->handToCourier($shipped, $this->manager);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_WITH_COURIER, $again->fulfillment_status);
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());
    }

    public function test_fulfillment_queue_routes(): void
    {
        $preparing = $this->invoices->confirm($this->draft(2), $this->manager);
        $withCourier = $this->shipToCourier($this->draft(2), $this->manager);

        $this->actingAs($this->manager)
            ->get(route('gross.sales.fulfillment', 'preparing'))
            ->assertOk()
            ->assertSee($preparing->invoice_number)
            ->assertDontSee($withCourier->invoice_number);

        $this->actingAs($this->manager)
            ->get(route('gross.sales.fulfillment', 'with_courier'))
            ->assertOk()
            ->assertSee($withCourier->invoice_number)
            ->assertDontSee($preparing->invoice_number);
    }

    public function test_cancel_allowed_while_preparing(): void
    {
        $confirmed = $this->invoices->confirm($this->draft(2), $this->manager);
        $cancelled = $this->invoices->cancel($confirmed);
        $this->assertSame(GrossSalesInvoice::STATUS_CANCELLED, $cancelled->status);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(20, $balance->qty_on_hand_pieces);
    }

    private function draft(int $pieces): GrossSalesInvoice
    {
        return $this->invoices->createDraft($this->manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $this->shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 25,
        ], [[
            'item_id' => $this->item->id,
            'qty' => $pieces,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
    }
}
