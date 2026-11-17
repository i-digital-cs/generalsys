<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Support\GeneralHouse\GhAttemptReason;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner rule: successful driver actions return to the workspace list, not the task detail URL.
 */
class DriverPostActionNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments', 'driver_cash_collections', 'driver_cash_settlements'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        config([
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.standard_vat_rate' => '5.00',
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.driver_collection_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
        ]);
    }

    public function test_driver_confirm_pickup_redirects_to_pickup_list_with_flash(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->makePickupOrder($driver);

        $this->actingAs($driver)
            ->from(route('gh.driver.pickup', $order))
            ->post(route('gh.orders.confirm-pickup', $order), ['notes' => 'ok'])
            ->assertRedirect(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertSessionHas('success');

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->actingAs($driver)->get(route('gh.driver.pickup', $order))->assertOk();
    }

    public function test_driver_failed_pickup_redirects_to_list(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->makePickupOrder($driver);

        $this->actingAs($driver)
            ->post(route('gh.orders.failed-pickup', $order), [
                'reason_code' => GhAttemptReason::CUSTOMER_UNAVAILABLE,
                'notes' => 'busy',
            ])
            ->assertRedirect(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertSessionHas('success');
    }

    public function test_driver_confirm_delivery_cash_and_online_redirect_to_delivery_list(): void
    {
        $driver = User::factory()->driver()->create();
        $cashCtx = $this->makeDeliveryContext($driver, '40.00');
        $this->actingAs($driver)
            ->from(route('gh.driver.delivery', $cashCtx['order']))
            ->post(route('gh.orders.confirm-delivery', $cashCtx['order']), [
                'payment_method' => 'CASH',
                'cash_collected_amount' => '42.00',
            ])
            ->assertRedirect(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertSessionHas('success');

        $this->assertSame(1, GhDriverCashCollection::query()->where('order_id', $cashCtx['order']->id)->count());
        $this->assertSame(GhOrderStatus::COMPLETED, $cashCtx['order']->fresh()->status);

        // Idempotent retry: no second custody row; still lands on delivery list.
        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $cashCtx['order']), [
                'payment_method' => 'CASH',
                'cash_collected_amount' => '42.00',
            ])
            ->assertRedirect(route('gh.driver.dashboard', ['tab' => 'delivery']));
        $this->assertSame(1, GhDriverCashCollection::query()->where('order_id', $cashCtx['order']->id)->count());

        $onlineCtx = $this->makeDeliveryContext($driver, '30.00');
        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $onlineCtx['order']), [
                'payment_method' => 'ONLINE',
            ])
            ->assertRedirect(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertSessionHas('success');
        $this->assertSame(0, GhDriverCashCollection::query()->where('order_id', $onlineCtx['order']->id)->count());
    }

    public function test_delivery_validation_failure_preserves_input_errors(): void
    {
        $driver = User::factory()->driver()->create();
        $ctx = $this->makeDeliveryContext($driver, '40.00');

        $this->actingAs($driver)
            ->from(route('gh.driver.delivery', $ctx['order']))
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), [
                'payment_method' => 'CASH',
                'cash_collected_amount' => '0',
            ])
            ->assertRedirect(route('gh.driver.delivery', $ctx['order']))
            ->assertSessionHasErrors()
            ->assertSessionHasInput('payment_method', 'CASH');

        $this->assertSame(GhOrderStatus::DELIVERY, $ctx['order']->fresh()->status);
        $this->assertSame(0, GhDriverCashCollection::query()->where('order_id', $ctx['order']->id)->count());
    }

    public function test_driver_forbidden_from_management_offerings(): void
    {
        $driver = User::factory()->driver()->create();
        $this->actingAs($driver)->get(route('gh.offerings.index'))->assertForbidden();
    }

    private function makePickupOrder(User $driver): GhOrder
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);

        return GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::now($tz)->subHour(),
            'pickup_ends_at' => Carbon::now($tz)->addHour(),
        ]);
    }

    /**
     * @return array{order: GhOrder, invoice: GhInvoice}
     */
    private function makeDeliveryContext(User $driver, string $unitPrice): array
    {
        $manager = User::factory()->manager()->create();
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => $unitPrice]);

        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now('UTC'),
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::now($tz)->subDay()->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->subDay()->setTime(12, 0),
            'created_by' => $manager->id,
        ]);

        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => 1,
            'unit_price_estimate' => $unitPrice,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
        ]);

        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $offering->id,
            'quantity' => 1,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'unit_price_estimate' => $unitPrice,
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);
        $invoice = app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
        $order->forceFill(['status' => GhOrderStatus::DELIVERY])->save();

        return ['order' => $order->fresh(), 'invoice' => $invoice];
    }
}
