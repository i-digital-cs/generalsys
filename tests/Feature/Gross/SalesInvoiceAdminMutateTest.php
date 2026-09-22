<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceMedia;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceMediaService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

/**
 * T-15.5 — sales update/delete any status: Manager (Admin) only.
 */
class SalesInvoiceAdminMutateTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    private User $manager;

    private User $accountant;

    private User $sales;

    private GrossSalesInvoice $draft;

    private int $itemId;

    private int $shippingId;

    private SalesInvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->manager = User::factory()->manager()->create();
        $this->accountant = User::factory()->accountant()->create();
        $this->sales = User::factory()->sales()->create();
        $this->invoices = app(SalesInvoiceService::class);

        foreach ([$this->accountant, $this->sales] as $user) {
            ValuationAssignment::query()->create([
                'user_id' => $user->id,
                'assigned_by' => $this->manager->id,
                'module' => 'product',
            ]);
        }

        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'pieces_per_carton' => 5,
            'reorder_level' => 2,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 30, GrossStockMovement::UNIT_PIECE, $this->manager);
        $this->itemId = $item->id;

        app(ShippingCompanyService::class)->create([
            'name' => 'Bosta',
            'status' => 'ACTIVE',
        ]);
        $this->shippingId = (int) GrossShippingCompany::query()->value('id');

        $this->draft = $this->invoices->createDraft($this->manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $this->shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 10,
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]);
    }

    public function test_sales_and_accountant_cannot_mutate_or_delete(): void
    {
        foreach ([$this->sales, $this->accountant] as $actor) {
            $this->actingAs($actor->fresh())
                ->get(route('gross.sales.edit', $this->draft))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->delete(route('gross.sales.destroy', $this->draft))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->post(route('gross.sales.confirm', $this->draft))
                ->assertForbidden();
        }

        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $this->draft->fresh()->status);
    }

    public function test_manager_can_update_and_delete_draft(): void
    {
        $this->actingAs($this->manager)
            ->put(route('gross.sales.update', $this->draft), $this->payload('Ali Updated', 3, 55))
            ->assertRedirect(route('gross.sales.show', $this->draft));

        $this->assertSame('Ali Updated', $this->draft->fresh()->customer_name);
        $this->assertSame(3, (int) $this->draft->fresh()->lines->sum('qty_pieces'));

        $this->actingAs($this->manager)
            ->delete(route('gross.sales.destroy', $this->draft->fresh()))
            ->assertRedirect(route('gross.sales.index'));

        $this->assertDatabaseMissing('gross_sales_invoices', ['id' => $this->draft->id]);
    }

    public function test_manager_can_update_and_delete_with_courier_restoring_stock(): void
    {
        $confirmed = $this->invoices->confirm($this->draft, $this->manager);
        $shipped = $this->shipToCourier($confirmed, $this->manager);

        $balance = GrossStockBalance::query()->where('item_id', $this->itemId)->firstOrFail();
        $this->assertSame(28, $balance->qty_on_hand_pieces);
        $this->assertSame(2, $balance->qty_with_courier_pieces);

        $this->actingAs($this->manager)
            ->put(route('gross.sales.update', $shipped), $this->payload('Ali Shipped', 4, 60))
            ->assertRedirect(route('gross.sales.show', $shipped));

        $shipped->refresh();
        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $shipped->status);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_PREPARING, $shipped->fulfillment_status);
        $this->assertSame('Ali Shipped', $shipped->customer_name);
        $this->assertSame(4, (int) $shipped->lines->sum('qty_pieces'));
        $this->assertNull($shipped->lines->first()->allocation);

        $balance->refresh();
        $this->assertSame(30, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);

        $this->actingAs($this->manager)
            ->delete(route('gross.sales.destroy', $shipped))
            ->assertRedirect(route('gross.sales.index'));

        $this->assertDatabaseMissing('gross_sales_invoices', ['id' => $shipped->id]);
        $this->assertSame(30, (int) GrossStockBalance::query()->where('item_id', $this->itemId)->value('qty_on_hand_pieces'));
    }

    public function test_manager_delete_delivered_restores_on_hand(): void
    {
        $confirmed = $this->invoices->confirm($this->draft, $this->manager);
        $shipped = $this->shipToCourier($confirmed, $this->manager);
        $this->invoices->collectCod($shipped->fresh(), $this->manager);
        $this->invoices->markDelivered($shipped->fresh(), $this->manager);

        $balance = GrossStockBalance::query()->where('item_id', $this->itemId)->firstOrFail();
        $this->assertSame(28, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);

        $this->actingAs($this->manager)
            ->delete(route('gross.sales.destroy', $shipped->fresh()))
            ->assertRedirect(route('gross.sales.index'));

        $balance->refresh();
        $this->assertSame(30, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $this->assertSame(0, $balance->qty_lost_pieces);
    }

    public function test_sales_can_create_draft_but_not_confirm(): void
    {
        $this->actingAs($this->sales->fresh())
            ->post(route('gross.sales.store'), $this->payload('Buyer', 1, 40))
            ->assertRedirect();

        $created = GrossSalesInvoice::query()->where('customer_name', 'Buyer')->firstOrFail();
        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $created->status);

        $this->actingAs($this->sales->fresh())
            ->post(route('gross.sales.confirm', $created))
            ->assertForbidden();
    }

    public function test_sales_cannot_delete_media_manager_can(): void
    {
        $media = app(SalesInvoiceMediaService::class)->store(
            $this->draft,
            UploadedFile::fake()->image('x.jpg', 80, 80),
            $this->manager,
            null,
            null,
        );

        $this->actingAs($this->sales->fresh())
            ->delete(route('gross.sales.media.destroy', [$this->draft, $media]))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->delete(route('gross.sales.media.destroy', [$this->draft, $media]))
            ->assertRedirect();

        $this->assertSame(0, GrossSalesInvoiceMedia::query()->count());
    }

    public function test_index_shows_edit_delete_for_manager_on_confirmed(): void
    {
        $confirmed = $this->invoices->confirm($this->draft, $this->manager);

        $this->actingAs($this->manager)
            ->get(route('gross.sales.index'))
            ->assertOk()
            ->assertSee(route('gross.sales.edit', $confirmed), false)
            ->assertSee(route('gross.sales.destroy', $confirmed), false);

        $this->actingAs($this->sales->fresh())
            ->get(route('gross.sales.index'))
            ->assertOk()
            ->assertDontSee(route('gross.sales.edit', $confirmed), false);
    }

    /** @return array<string, mixed> */
    private function payload(string $customer, int $qty, float $price): array
    {
        return [
            'customer_name' => $customer,
            'customer_phone' => '0100',
            'shipping_company_id' => $this->shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 10,
            'discount_mode' => 'amount',
            'discount_amount' => 0,
            'lines' => [[
                'item_id' => $this->itemId,
                'qty' => $qty,
                'unit' => 'PIECE',
                'unit_price' => $price,
                'discount_mode' => 'amount',
                'discount_amount' => 0,
            ]],
        ];
    }
}
