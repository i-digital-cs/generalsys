<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemAttributeService;
use App\Services\Gross\ItemService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesCashierTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_page_ok_for_manager(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.cashier'))
            ->assertOk()
            ->assertSee(__('gross.sales.cashier_title'), false)
            ->assertSee(route('gross.sales.cashier.categories', [], false), false)
            ->assertSee(route('gross.sales.store', [], false), false);
    }

    public function test_index_shows_cashier_button(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.index'))
            ->assertOk()
            ->assertSee(route('gross.sales.cashier', [], false), false)
            ->assertSee(__('gross.sales.cashier'), false)
            ->assertSee(route('gross.sales.create', [], false), false);
    }

    public function test_catalog_json_endpoints(): void
    {
        $manager = User::factory()->manager()->create();
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'ملابس', 'name_en' => 'Clothing']);
        $sub = $cats->create([
            'name_ar' => 'فساتين',
            'name_en' => 'Dresses',
            'parent_id' => $root->id,
        ]);
        $size = app(ItemAttributeService::class)->create([
            'kind' => GrossItemAttribute::KIND_SIZE,
            'name_ar' => 'وسط',
            'name_en' => 'M',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Blue Dress',
            'subcategory_id' => $sub->id,
            'size_id' => $size->id,
            'default_sale_price' => 55,
            'status' => GrossItem::STATUS_ACTIVE,
            'description' => 'Nice blue',
        ]);

        $this->actingAs($manager)
            ->getJson(route('gross.sales.cashier.categories'))
            ->assertOk()
            ->assertJsonFragment(['id' => $root->id]);

        $this->actingAs($manager)
            ->getJson(route('gross.sales.cashier.subcategories', ['category_id' => $root->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $sub->id]);

        $this->actingAs($manager)
            ->getJson(route('gross.sales.cashier.products', ['subcategory_id' => $sub->id]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Blue Dress', 'variant_count' => 1]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->getJson(route('gross.sales.cashier.variants', [
                'name' => 'Blue Dress',
                'subcategory_id' => $sub->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $item->id, 'size' => 'M']);
    }

    public function test_catalog_requires_auth(): void
    {
        $this->getJson(route('gross.sales.cashier.categories'))
            ->assertUnauthorized();
    }

    public function test_team_leader_forbidden_from_cashier(): void
    {
        $tl = User::factory()->teamLeader()->create();

        $this->actingAs($tl)
            ->get(route('gross.sales.cashier'))
            ->assertForbidden();

        $this->actingAs($tl)
            ->getJson(route('gross.sales.cashier.categories'))
            ->assertForbidden();
    }

    public function test_store_from_cashier_shaped_payload_creates_draft(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'POS Item',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 40,
        ]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);
        $shippingId = app(ShippingCompanyService::class)->create([
            'name' => 'Ship Co',
            'status' => 'ACTIVE',
        ])->id;

        $this->actingAs($manager)
            ->post(route('gross.sales.store'), [
                'customer_name' => 'Walk-in',
                'customer_phone' => '0111222333',
                'shipping_company_id' => $shippingId,
                'currency' => 'EGP',
                'shipping_fee' => 15,
                'discount_mode' => 'amount',
                'discount_amount' => 0,
                'lines' => [[
                    'item_id' => $item->id,
                    'qty' => 2,
                    'unit' => 'PIECE',
                    'unit_price' => 40,
                    'discount_mode' => 'amount',
                    'discount_amount' => 0,
                    'discount_percent' => 0,
                ]],
            ])
            ->assertRedirect();

        $invoice = GrossSalesInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame($item->id, (int) $invoice->lines()->first()->item_id);
        $this->assertSame(2, (int) $invoice->lines()->first()->qty);
    }
}
