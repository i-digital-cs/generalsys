<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Orders\GhDriverWorkspaceService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackagePickupStaffCountingTest extends TestCase
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

    public function test_package_pickup_then_staff_counts_six_garments_and_invoices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 12:00:00', 'Asia/Dubai'));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);

        $kandura = GhProductOffering::factory()->create(['standard_price' => '15.00']);
        $shirt = GhProductOffering::factory()->create(['standard_price' => '10.00']);
        $suit = GhProductOffering::factory()->create(['standard_price' => '25.00']);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'scheduled_pickup_at' => '2026-11-07T14:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect();

        $order = GhOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(GhOrderStatus::PICKUP, $order->status);
        $this->assertSame(0, $order->lines()->count());

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-pickup', $order), ['notes' => 'bag sealed'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertSame(0, $order->receivedLines()->count());
        $this->assertNotNull($order->picked_up_at);
        $this->assertSame((int) $driver->id, (int) $order->pickup_confirmed_by);

        // Direct invoice entry — no separate received counting step.
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $this->assertSame(0, $draft->lines()->count());

        $this->actingAs($manager)->put(route('gh.invoices.update', $draft), [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_override_type' => 'APPLY_DEFAULT',
            'lines' => [
                ['offering_id' => $kandura->id, 'quantity' => 3],
                ['offering_id' => $shirt->id, 'quantity' => 2],
                ['offering_id' => $suit->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $draft->refresh();
        $this->assertSame(6, (int) $draft->lines()->sum('quantity'));
        $this->assertSame(0, GhOrderReceivedLine::query()->where('order_id', $order->id)->count());

        $html = $this->actingAs($manager)->get(route('gh.invoices.builder', $draft))->assertOk()->getContent();
        $this->assertStringContainsString('gh-catalog-grid', $html);

        $issued = app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertNotNull($issued->issued_at);

        Carbon::setTestNow();
    }

    public function test_staff_picker_includes_active_products_without_offerings(): void
    {
        $category = GhServiceCategory::factory()->create(['status' => GhCatalogStatus::ACTIVE]);
        $withOffering = GhProduct::factory()->create([
            'category_id' => $category->id,
            'status' => GhCatalogStatus::ACTIVE,
            'code' => 'HAS-OFF',
        ]);
        $treatment = GhTreatment::factory()->create(['status' => GhCatalogStatus::ACTIVE]);
        GhProductOffering::factory()->create([
            'product_id' => $withOffering->id,
            'treatment_id' => $treatment->id,
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '5.00',
        ]);
        $without = GhProduct::factory()->create([
            'category_id' => $category->id,
            'status' => GhCatalogStatus::ACTIVE,
            'code' => 'NO-OFF',
        ]);
        GhProduct::factory()->create([
            'category_id' => $category->id,
            'status' => GhCatalogStatus::INACTIVE,
            'code' => 'INACTIVE',
        ]);

        $groups = app(GhDriverWorkspaceService::class)->staffCountingProductGroups();
        $codes = collect($groups)->pluck('code')->all();
        $this->assertContains('HAS-OFF', $codes);
        $this->assertContains('NO-OFF', $codes);
        $this->assertNotContains('INACTIVE', $codes);

        $noOff = collect($groups)->firstWhere('code', 'NO-OFF');
        $this->assertSame([], $noOff['treatments']);
    }

    public function test_empty_draft_allowed_but_issuance_blocked(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create(['min_order_amount' => '0.00']);
        $customer = GhCustomer::factory()->create();
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $order = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'pickup_driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $this->assertSame(0, $draft->lines()->count());

        $this->expectException(ValidationException::class);
        app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }

    public function test_driver_forbidden_from_staff_received_sync(): void
    {
        $driver = User::factory()->driver()->create();
        $offering = GhProductOffering::factory()->create(['standard_price' => '9.00']);
        $order = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'pickup_driver_id' => $driver->id,
        ]);

        $this->actingAs($driver)
            ->post(route('gh.orders.received-lines.sync', $order), [
                'received' => [['offering_id' => $offering->id, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }
}
