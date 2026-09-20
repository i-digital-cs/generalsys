<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemImage;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_multiple_images_on_create_and_view_show(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();

        $files = [
            UploadedFile::fake()->image('a.jpg', 100, 100),
            UploadedFile::fake()->image('b.png', 120, 120),
        ];

        $this->actingAs($manager)
            ->post(route('gross.items.store'), [
                'name' => 'Photo Item',
                'reorder_level' => 5,
                'status' => 'ACTIVE',
                'images' => $files,
            ])
            ->assertRedirect();

        $item = GrossItem::query()->where('name', 'Photo Item')->firstOrFail();
        $this->assertSame(2, $item->images()->count());

        $image = $item->images()->firstOrFail();
        Storage::disk('local')->assertExists($image->file_path);

        $this->actingAs($manager)
            ->get(route('gross.items.show', $item))
            ->assertOk()
            ->assertSee(__('gross.items.details'), false)
            ->assertSee(route('gross.items.images.show', [$item, $image]), false);

        $this->actingAs($manager)
            ->get(route('gross.items.images.show', [$item, $image]))
            ->assertOk();
    }

    public function test_can_remove_image_on_update(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();

        $item = app(ItemService::class)->create(
            [
                'name' => 'Removable',
                'reorder_level' => 5,
                'status' => GrossItem::STATUS_ACTIVE,
            ],
            [UploadedFile::fake()->image('keep.jpg'), UploadedFile::fake()->image('drop.jpg')]
        );

        $drop = $item->images()->skip(1)->firstOrFail();
        $path = $drop->file_path;

        $this->actingAs($manager)
            ->put(route('gross.items.update', $item), [
                'name' => 'Removable',
                'reorder_level' => 5,
                'status' => 'ACTIVE',
                'remove_image_ids' => [$drop->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('gross_item_images', ['id' => $drop->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, GrossItemImage::query()->where('item_id', $item->id)->count());
    }

    public function test_search_json_includes_image_url_when_item_has_image(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();

        $item = app(ItemService::class)->create(
            [
                'name' => 'Searchable Photo Dress',
                'status' => GrossItem::STATUS_ACTIVE,
                'default_sale_price' => 99,
            ],
            [UploadedFile::fake()->image('dress.jpg')]
        );
        $image = $item->images()->firstOrFail();

        $this->actingAs($manager)
            ->getJson(route('gross.items.search', ['q' => 'Searchable Photo']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $item->id,
                'image_url' => route('gross.items.images.show', [$item, $image]),
            ]);
    }

    public function test_sales_and_purchase_invoice_show_include_item_image(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();
        $stock = app(StockService::class);

        $item = app(ItemService::class)->create(
            [
                'name' => 'Invoice Photo Item',
                'status' => GrossItem::STATUS_ACTIVE,
                'default_sale_price' => 50,
            ],
            [UploadedFile::fake()->image('inv.jpg')]
        );
        $image = $item->images()->firstOrFail();
        $imageUrl = route('gross.items.images.show', [$item, $image], false);
        $stock->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $supplier = GrossSupplier::query()->create([
            'name' => 'Supp',
            'phone' => '01009998877',
        ]);
        $purchase = app(PurchaseInvoiceService::class)->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'buy_price' => 20,
            'sale_price' => 50,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $shippingId = app(ShippingCompanyService::class)->create([
            'name' => 'Ship',
            'status' => 'ACTIVE',
        ])->id;
        $sale = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'Client',
            'customer_phone' => '0100',
            'shipping_company_id' => $shippingId,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 50,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $this->actingAs($manager)
            ->get(route('gross.sales.show', $sale))
            ->assertOk()
            ->assertSee($imageUrl, false);

        $this->actingAs($manager)
            ->get(route('gross.purchases.show', $purchase))
            ->assertOk()
            ->assertSee($imageUrl, false);
    }
}
