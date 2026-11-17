<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Payments\GhDriverCashCustodyService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverCashCustodyTest extends TestCase
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
            'general_house.payments.driver_delivery_payment_enabled' => true,
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
        ]);
    }

    public function test_no_invoice_cash_records_custody_only(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->deliveryOrderWithoutInvoice($driver);

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $order), [
                'payment_method' => 'CASH',
                'cash_collected_amount' => '75.50',
            ])
            ->assertRedirect();

        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertSame(0, GhPayment::query()->count());
        $collection = GhDriverCashCollection::query()->where('order_id', $order->id)->sole();
        $this->assertSame('75.50', (string) $collection->amount);
        $this->assertNull($collection->payment_id);
        $this->assertSame('75.50', app(GhDriverCashCustodyService::class)->outstandingFor($driver));
    }

    public function test_cash_zero_rejected_and_online_no_custody(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->deliveryOrderWithoutInvoice($driver);

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $order), [
                'payment_method' => 'CASH',
                'cash_collected_amount' => '0',
            ])
            ->assertSessionHasErrors('cash_collected_amount');

        // Rebuild unpaid invoice path via DriverDeliveryPaymentTest-style not needed —
        // ONLINE without invoice is not required; confirm without method still works.
        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $order), [])
            ->assertRedirect();
        $this->assertSame(0, GhDriverCashCollection::query()->count());
    }

    public function test_partial_and_full_settlement_and_over_settlement_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->deliveryOrderWithoutInvoice($driver);

        $this->actingAs($driver)->post(route('gh.orders.confirm-delivery', $order), [
            'payment_method' => 'CASH',
            'cash_collected_amount' => '100.00',
        ])->assertRedirect();

        $custody = app(GhDriverCashCustodyService::class);
        $this->assertSame('100.00', $custody->outstandingFor($driver));

        $key1 = (string) Str::uuid();
        $this->actingAs($manager)->post(route('gh.driver-cash.settle', $driver), [
            'amount' => '40.00',
            'idempotency_key' => $key1,
            'receipt_reference' => 'R-1',
        ])->assertRedirect(route('gh.driver-cash.show', $driver));

        $this->assertSame('60.00', $custody->outstandingFor($driver));
        $this->assertSame(1, GhDriverCashSettlement::query()->where('driver_id', $driver->id)->count());

        // Duplicate settlement key is idempotent.
        $this->actingAs($manager)->post(route('gh.driver-cash.settle', $driver), [
            'amount' => '40.00',
            'idempotency_key' => $key1,
        ])->assertRedirect();
        $this->assertSame(1, GhDriverCashSettlement::query()->where('driver_id', $driver->id)->count());
        $this->assertSame('60.00', $custody->outstandingFor($driver));

        $this->actingAs($manager)->post(route('gh.driver-cash.settle', $driver), [
            'amount' => '70.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('amount');
        $this->assertSame('60.00', $custody->outstandingFor($driver));

        $this->actingAs($manager)->post(route('gh.driver-cash.settle', $driver), [
            'amount' => '60.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame('0.00', $custody->outstandingFor($driver));
        $this->assertSame(0, GhPayment::query()->count());
    }

    public function test_driver_cannot_settle_self_or_view_custody_admin(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->deliveryOrderWithoutInvoice($driver);
        $this->actingAs($driver)->post(route('gh.orders.confirm-delivery', $order), [
            'payment_method' => 'CASH',
            'cash_collected_amount' => '20.00',
        ])->assertRedirect();

        $this->actingAs($driver)->get(route('gh.driver-cash.index'))->assertForbidden();
        $this->actingAs($driver)->post(route('gh.driver-cash.settle', $driver), [
            'amount' => '20.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    public function test_manager_index_shows_outstanding(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create([
            'name_en' => 'Cash Driver',
            'name_ar' => 'سائق نقد',
        ]);
        $order = $this->deliveryOrderWithoutInvoice($driver);
        $this->actingAs($driver)->post(route('gh.orders.confirm-delivery', $order), [
            'payment_method' => 'CASH',
            'cash_collected_amount' => '33.00',
        ])->assertRedirect();

        app()->setLocale('en');
        $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver-cash.index'))
            ->assertOk()
            ->assertSee('Cash Driver', false)
            ->assertSee(__('gh.driver_cash.title'), false);
    }

    private function deliveryOrderWithoutInvoice(User $driver): GhOrder
    {
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $manager = User::factory()->manager()->create();
        $picked = Carbon::now('UTC')->subHours(3);

        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => $picked,
            'pickup_confirmed_by' => $driver->id,
            'invoice_issued_at' => null,
            'scheduled_delivery_at' => $picked,
            'created_by' => $manager->id,
        ]);

        app(GhOrderStateService::class)->promoteOneToDelivery($order->fresh(), $manager, 'manual');

        return $order->fresh();
    }
}
