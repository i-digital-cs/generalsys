<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceMedia;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\GrossUserPageService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceMediaService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesInvoiceMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private GrossSalesInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->manager = User::factory()->manager()->create();

        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'pieces_per_carton' => 5,
            'reorder_level' => 2,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);

        $this->invoice = app(SalesInvoiceService::class)->createDraft($this->manager, [
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

    public function test_manager_can_upload_view_and_delete_media_with_optional_meta(): void
    {
        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->post(route('gross.sales.media.store', $this->invoice), [
                'image' => UploadedFile::fake()->image('photo.jpg', 200, 200),
                'title' => 'Before packing',
                'description' => 'Customer WhatsApp photo',
            ])
            ->assertRedirect(route('gross.sales.show', $this->invoice));

        $media = GrossSalesInvoiceMedia::query()->firstOrFail();
        $this->assertSame('Before packing', $media->title);
        $this->assertSame('Customer WhatsApp photo', $media->description);
        Storage::disk('local')->assertExists($media->file_path);

        $this->actingAs($this->manager)
            ->get(route('gross.sales.show', $this->invoice))
            ->assertOk()
            ->assertSee('Invoice media', false)
            ->assertSee('Before packing', false)
            ->assertSee('Customer WhatsApp photo', false);

        $this->actingAs($this->manager)
            ->get(route('gross.sales.media.show', [$this->invoice, $media]))
            ->assertOk();

        $this->actingAs($this->manager)
            ->delete(route('gross.sales.media.destroy', [$this->invoice, $media]))
            ->assertRedirect(route('gross.sales.show', $this->invoice));

        $this->assertSame(0, GrossSalesInvoiceMedia::query()->count());
        Storage::disk('local')->assertMissing($media->file_path);
    }

    public function test_sales_with_page_can_upload_media(): void
    {
        $sales = User::factory()->sales()->create();
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $this->manager->id,
            'module' => 'product',
        ]);
        app(GrossUserPageService::class)->applyRoleDefaults($sales);

        $confirmed = app(SalesInvoiceService::class)->confirm($this->invoice, $this->manager);

        $this->actingAs($sales->fresh())
            ->post(route('gross.sales.media.store', $confirmed), [
                'image' => UploadedFile::fake()->image('note.png', 100, 100),
            ])
            ->assertRedirect(route('gross.sales.show', $confirmed));

        $this->assertSame(1, GrossSalesInvoiceMedia::query()->count());
    }

    public function test_employee_cannot_manage_media(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->post(route('gross.sales.media.store', $this->invoice), [
                'image' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame(0, GrossSalesInvoiceMedia::query()->count());
    }

    public function test_rejects_non_image_and_enforces_max(): void
    {
        $this->actingAs($this->manager)
            ->from(route('gross.sales.show', $this->invoice))
            ->post(route('gross.sales.media.store', $this->invoice), [
                'image' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('image');

        $svc = app(SalesInvoiceMediaService::class);
        for ($i = 0; $i < SalesInvoiceMediaService::MAX_PER_INVOICE; $i++) {
            $svc->store(
                $this->invoice,
                UploadedFile::fake()->image("m{$i}.jpg"),
                $this->manager
            );
        }

        $this->actingAs($this->manager)
            ->from(route('gross.sales.show', $this->invoice))
            ->post(route('gross.sales.media.store', $this->invoice), [
                'image' => UploadedFile::fake()->image('overflow.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('image');

        $this->assertSame(
            SalesInvoiceMediaService::MAX_PER_INVOICE,
            GrossSalesInvoiceMedia::query()->count()
        );
    }

    public function test_media_route_scoped_to_invoice(): void
    {
        $other = app(SalesInvoiceService::class)->createDraft($this->manager, [
            'customer_name' => 'Other',
            'customer_phone' => '0111',
            'currency' => 'EGP',
        ], [[
            'item_id' => $this->invoice->lines->first()->item_id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]);

        $media = app(SalesInvoiceMediaService::class)->store(
            $this->invoice,
            UploadedFile::fake()->image('a.jpg'),
            $this->manager,
            'Scoped'
        );

        $this->actingAs($this->manager)
            ->get(route('gross.sales.media.show', [$other, $media]))
            ->assertNotFound();
    }
}
