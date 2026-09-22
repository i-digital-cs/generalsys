<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceMedia;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceMediaService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-15.3 — all sales-invoice mutations: Manager (Admin) only; create stays maySell.
 */
class SalesInvoiceAdminMutateTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $accountant;

    private User $sales;

    private GrossSalesInvoice $draft;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->manager = User::factory()->manager()->create();
        $this->accountant = User::factory()->accountant()->create();
        $this->sales = User::factory()->sales()->create();

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
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $this->manager);
        $this->itemId = $item->id;

        $this->draft = app(SalesInvoiceService::class)->createDraft($this->manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]);
    }

    public function test_sales_and_accountant_cannot_mutate_invoice(): void
    {
        foreach ([$this->sales, $this->accountant] as $actor) {
            $this->actingAs($actor->fresh())
                ->get(route('gross.sales.edit', $this->draft))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->post(route('gross.sales.cancel', $this->draft))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->post(route('gross.sales.confirm', $this->draft))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->post(route('gross.sales.media.store', $this->draft), [
                    'image' => UploadedFile::fake()->image('x.jpg', 60, 60),
                ])
                ->assertForbidden();
        }

        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $this->draft->fresh()->status);
        $this->assertSame(0, GrossSalesInvoiceMedia::query()->count());
    }

    public function test_manager_can_edit_confirm_and_cancel(): void
    {
        $this->actingAs($this->manager)
            ->get(route('gross.sales.edit', $this->draft))
            ->assertOk();

        $this->actingAs($this->manager)
            ->put(route('gross.sales.update', $this->draft), [
                'customer_name' => 'Ali Updated',
                'customer_phone' => '0100',
                'currency' => 'EGP',
                'discount_mode' => 'amount',
                'discount_amount' => 0,
                'lines' => [[
                    'item_id' => $this->itemId,
                    'qty' => 1,
                    'unit' => 'PIECE',
                    'unit_price' => 55,
                    'discount_mode' => 'amount',
                    'discount_amount' => 0,
                ]],
            ])
            ->assertRedirect(route('gross.sales.show', $this->draft));

        $this->assertSame('Ali Updated', $this->draft->fresh()->customer_name);

        $this->actingAs($this->manager)
            ->post(route('gross.sales.confirm', $this->draft->fresh()))
            ->assertRedirect();

        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $this->draft->fresh()->status);

        $this->actingAs($this->manager)
            ->post(route('gross.sales.cancel', $this->draft->fresh()))
            ->assertRedirect();

        $this->assertSame(GrossSalesInvoice::STATUS_CANCELLED, $this->draft->fresh()->status);
    }

    public function test_sales_can_create_draft_but_not_confirm(): void
    {
        $this->actingAs($this->sales->fresh())
            ->get(route('gross.sales.create'))
            ->assertOk();

        $this->actingAs($this->sales->fresh())
            ->post(route('gross.sales.store'), [
                'customer_name' => 'Buyer',
                'customer_phone' => '0111',
                'currency' => 'EGP',
                'discount_mode' => 'amount',
                'discount_amount' => 0,
                'lines' => [[
                    'item_id' => $this->itemId,
                    'qty' => 1,
                    'unit' => 'PIECE',
                    'unit_price' => 40,
                    'discount_mode' => 'amount',
                    'discount_amount' => 0,
                ]],
            ])
            ->assertRedirect();

        $created = GrossSalesInvoice::query()->where('customer_name', 'Buyer')->firstOrFail();
        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $created->status);

        $this->actingAs($this->sales->fresh())
            ->post(route('gross.sales.confirm', $created))
            ->assertForbidden();

        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $created->fresh()->status);
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
}
