<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DirectInvoiceEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => 'Asia/Dubai']);
        if (! DB::table('gh_sequences')->where('name', 'orders')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'orders',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_working_order_without_received_lines_opens_empty_draft(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->workingOrder(GhCustomerType::B2C);

        $this->actingAs($manager)
            ->post(route('gh.invoices.create-from-order', $order))
            ->assertRedirect();

        $invoice = GhInvoice::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(0, $invoice->lines()->count());
        $this->assertTrue($invoice->isDraft());

        $html = $this->actingAs($manager)->get(route('gh.invoices.builder', $invoice))->assertOk()->getContent();
        $this->assertStringContainsString('gh-catalog-grid', $html);
        $this->assertStringNotContainsString(__('gh.errors.invoice_requires_received'), $html);
    }

    public function test_b2c_catalog_includes_all_active_products_with_and_without_offerings(): void
    {
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $with = GhProductOffering::factory()->create(['standard_price' => '8.00']);
        $bare = GhProduct::factory()->create([
            'status' => GhCatalogStatus::ACTIVE,
            'code' => 'BARE-B2C',
        ]);
        $meta = app(GhPricingResolver::class)->invoiceBuilderCatalog($customer);
        $this->assertSame('B2C', $meta['mode']);
        $codes = collect($meta['groups'])->pluck('code')->all();
        $this->assertContains((string) $with->product->code, $codes);
        $this->assertContains('BARE-B2C', $codes);
        $bareGroup = collect($meta['groups'])->firstWhere('code', 'BARE-B2C');
        $this->assertSame([], $bareGroup['treatments']);
        $this->assertTrue((bool) ($bareGroup['setup_warning'] ?? false));
        $this->assertSame([], $meta['ooc_groups']);
        $this->assertNotNull($bare->id);
    }

    public function test_b2b_default_catalog_is_contract_only_and_ooc_requires_price(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $in = GhProductOffering::factory()->create(['standard_price' => '20.00']);
        $out = GhProductOffering::factory()->create(['standard_price' => '50.00']);
        $contract = GhB2bContract::factory()->create([
            'customer_id' => $customer->id,
            'status' => GhContractStatus::ACTIVE,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'reference' => 'GH-CTR-TEST01',
        ]);
        GhB2bContractOffering::factory()->create([
            'contract_id' => $contract->id,
            'offering_id' => $in->id,
            'contract_price' => '12.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $meta = app(GhPricingResolver::class)->invoiceBuilderCatalog($customer);
        $this->assertSame('B2B_CONTRACT', $meta['mode']);
        $contractOfferingIds = collect($meta['groups'])->flatMap(fn ($g) => collect($g['treatments'])->pluck('offering_id'))->all();
        $this->assertContains($in->id, $contractOfferingIds);
        $this->assertNotContains($out->id, $contractOfferingIds);
        $oocIds = collect($meta['ooc_groups'])->flatMap(fn ($g) => collect($g['treatments'])->pluck('offering_id'))->all();
        $this->assertContains($out->id, $oocIds);

        $order = $this->workingOrder(GhCustomerType::B2B, $customer, $contract->id);
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);

        $this->actingAs($manager)->put(route('gh.invoices.update', $draft), [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_override_type' => 'APPLY_DEFAULT',
            'lines' => [
                ['offering_id' => $in->id, 'quantity' => 1],
                [
                    'offering_id' => $out->id,
                    'quantity' => 1,
                    'out_of_contract' => '1',
                    'approve_price' => '1',
                    'unit_price' => '33.00',
                ],
            ],
        ])->assertRedirect();

        $draft->refresh();
        $oocLine = $draft->lines()->where('offering_id', $out->id)->firstOrFail();
        $this->assertSame('33.00', (string) $oocLine->unit_price);
        $this->assertTrue((bool) $oocLine->staff_price_approved);
        $this->assertFalse((bool) $oocLine->pricing_review_required);
        $this->assertSame('12.00', (string) $draft->lines()->where('offering_id', $in->id)->value('unit_price'));
    }

    public function test_empty_invoice_issuance_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->workingOrder(GhCustomerType::B2C);
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);

        try {
            app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
    }

    public function test_direct_entry_issue_moves_to_delivery_and_legacy_received_prefills(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 12:00:00', 'Asia/Dubai'));
        $manager = User::factory()->manager()->create();
        $order = $this->workingOrder(GhCustomerType::B2C);
        $a = GhProductOffering::factory()->create(['standard_price' => '10.00']);
        $b = GhProductOffering::factory()->create(['standard_price' => '11.00']);
        $c = GhProductOffering::factory()->create(['standard_price' => '12.00']);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);
        $this->actingAs($manager)->put(route('gh.invoices.update', $draft), [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_override_type' => 'APPLY_DEFAULT',
            'lines' => [
                ['offering_id' => $a->id, 'quantity' => 1],
                ['offering_id' => $b->id, 'quantity' => 2],
                ['offering_id' => $c->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $draft->refresh();
        app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);

        // Legacy received prefill path
        $legacy = $this->workingOrder(GhCustomerType::B2C);
        $off = GhProductOffering::factory()->create(['standard_price' => '9.00']);
        GhOrderReceivedLine::query()->create([
            'order_id' => $legacy->id,
            'offering_id' => $off->id,
            'quantity' => 4,
            'discrepancy_type' => GhReceivedDiscrepancy::ADDITIONAL,
            'unit_price_estimate' => '9.00',
            'currency' => 'AED',
            'price_source' => 'STANDARD',
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'sort_order' => 0,
        ]);
        $prefilled = app(GhInvoiceService::class)->createDraftFromOrder($legacy, $manager);
        $this->assertSame(1, $prefilled->lines()->count());
        $this->assertSame(4, (int) $prefilled->lines()->first()->quantity);
        Carbon::setTestNow();
    }

    public function test_ooc_pending_blocks_issuance(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $in = GhProductOffering::factory()->create(['standard_price' => '8.00']);
        $out = GhProductOffering::factory()->create(['standard_price' => '40.00']);
        $contract = GhB2bContract::factory()->create([
            'customer_id' => $customer->id,
            'status' => GhContractStatus::ACTIVE,
            'effective_from' => now()->subDay()->toDateString(),
        ]);
        GhB2bContractOffering::factory()->create([
            'contract_id' => $contract->id,
            'offering_id' => $in->id,
            'contract_price' => '5.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $order = $this->workingOrder(GhCustomerType::B2B, $customer, $contract->id);
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);

        $this->actingAs($manager)->put(route('gh.invoices.update', $draft), [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_override_type' => 'APPLY_DEFAULT',
            'lines' => [[
                'offering_id' => $out->id,
                'quantity' => 1,
                'out_of_contract' => '1',
                'approve_price' => '0',
            ]],
        ])->assertRedirect();

        $draft->refresh();
        $this->assertTrue((bool) $draft->lines()->first()->pricing_review_required);

        $this->expectException(ValidationException::class);
        app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }

    private function workingOrder(string $type, ?GhCustomer $customer = null, ?int $contractId = null): GhOrder
    {
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer ??= GhCustomer::factory()->create(['type' => $type]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);

        return GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'contract_id' => $contractId,
            'pickup_driver_id' => User::factory()->driver()->create()->id,
        ]);
    }
}
