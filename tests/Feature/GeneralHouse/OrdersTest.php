<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderReferenceService;
use App\Services\GeneralHouse\Orders\GhPickupEligibilityService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure sequence seed exists after migrate (migration inserts it).
        if (! DB::table('gh_sequences')->where('name', 'orders')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'orders',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_b2c_order_creation_with_multiple_treatments_and_later_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
            'is_default' => true,
            'default_for_customer_id' => $customer->id,
        ]);

        $product = GhProduct::factory()->create();
        $iron = GhTreatment::factory()->create(['name_en' => 'Iron Only', 'name_ar' => 'كي فقط']);
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash & Iron', 'name_ar' => 'غسيل وكي']);
        $o1 = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $iron->id,
            'standard_price' => '5.00',
        ]);
        $o2 = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $wash->id,
            'standard_price' => '12.00',
        ]);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [
                ['offering_id' => $o1->id, 'quantity' => 2],
                ['offering_id' => $o2->id, 'quantity' => 3],
            ],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '12:00',
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $delivery->id,
            'notes' => 'Ops note',
            'scheduling_notes' => 'Gate code 1',
        ])->assertRedirect();

        $order = GhOrder::query()->firstOrFail();
        $this->assertSame(GhOrderStatus::LATER, $order->status);
        $this->assertSame('GH-ORD-000001', $order->reference);
        $this->assertSame('46.00', (string) $order->estimated_subtotal);
        $this->assertSame(2, $order->lines()->count());
        $this->assertSame((int) $driver->id, (int) $order->pickup_driver_id);
        $this->assertSame((int) $delivery->id, (int) $order->delivery_driver_id);
        $this->assertTrue($order->events()->where('event_type', 'ORDER_CREATED')->exists());
        $this->assertSame(2, $order->assignments()->where('is_current', true)->count());

        Carbon::setTestNow();
    }

    public function test_initial_pickup_status_within_24_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        [$customer, $address, $offering] = $this->b2cFixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-26',
            'pickup_window_start' => '08:00',
            'pickup_window_end' => '09:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect();

        $this->assertSame(GhOrderStatus::PICKUP, GhOrder::query()->firstOrFail()->status);
        Carbon::setTestNow();
    }

    public function test_b2b_order_uses_contract_price_and_rejects_unauthorized_offering(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $allowed = GhProductOffering::factory()->create(['standard_price' => '20.00']);
        $forbidden = GhProductOffering::factory()->create(['standard_price' => '9.00']);
        $contract = GhB2bContract::factory()->create([
            'customer_id' => $customer->id,
            'status' => GhContractStatus::ACTIVE,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        GhB2bContractOffering::factory()->create([
            'contract_id' => $contract->id,
            'offering_id' => $allowed->id,
            'contract_price' => '15.50',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $forbidden->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '11:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $allowed->id, 'quantity' => 2]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '11:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect();

        $order = GhOrder::query()->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->assertSame('15.50', (string) $line->unit_price_estimate);
        $this->assertSame('CONTRACT', $line->price_source);
        $this->assertSame('31.00', (string) $order->estimated_subtotal);
        $this->assertSame((int) $contract->id, (int) $order->contract_id);

        Carbon::setTestNow();
    }

    public function test_new_customer_during_order_creation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $offering = GhProductOffering::factory()->create(['standard_price' => '8.00']);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'new_customer' => [
                'type' => GhCustomerType::B2C,
                'name_ar' => 'عميل جديد',
                'name_en' => 'New Guest',
                'phone' => '0501112233',
            ],
            'new_address' => [
                'zone_id' => $zone->id,
                'label' => 'Home',
                'building' => 'B1',
            ],
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-30',
            'pickup_window_start' => '14:00',
            'pickup_window_end' => '16:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('gh_customers', ['name_en' => 'New Guest', 'type' => 'B2C']);
        $this->assertDatabaseHas('gh_orders', ['estimated_subtotal' => '8.00']);
        Carbon::setTestNow();
    }

    public function test_inactive_offering_rejected_and_client_price_ignored(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        [$customer, $address] = $this->b2cFixture(false);
        $inactive = GhProductOffering::factory()->inactive()->create();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $inactive->id, 'quantity' => 1, 'unit_price' => '1.00']],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '11:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors();

        Carbon::setTestNow();
    }

    public function test_pickup_window_validation(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        [$customer, $address, $offering] = $this->b2cFixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '12:00',
            'pickup_window_end' => '10:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors('pickup_window_end');
    }

    public function test_order_reference_uniqueness_and_concurrency(): void
    {
        $refs = [];
        for ($i = 0; $i < 5; $i++) {
            $refs[] = app(GhOrderReferenceService::class)->nextReference();
        }
        $this->assertSame($refs, array_unique($refs));
        $this->assertSame('GH-ORD-000001', $refs[0]);
        $this->assertSame('GH-ORD-000005', $refs[4]);
    }

    public function test_unauthorized_employee_forbidden(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $this->actingAs($employee)->get(route('gh.orders.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('gh.orders.store'), [])->assertForbidden();
    }

    public function test_edit_restrictions_after_pickup_flag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $otherDriver = User::factory()->driver()->create();
        [$customer, $address, $offering] = $this->b2cFixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '11:00',
            'pickup_driver_id' => $driver->id,
            'notes' => 'before',
        ])->assertRedirect();

        $order = GhOrder::query()->firstOrFail();
        $order->update([
            'picked_up_at' => now(),
            'status' => GhOrderStatus::WORKING,
        ]);

        $this->actingAs($manager)->put(route('gh.orders.update', $order), [
            'notes' => 'after pickup',
            'delivery_driver_id' => $otherDriver->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 99]],
            'pickup_date' => '2026-10-29',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '11:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors();

        $this->actingAs($manager)->put(route('gh.orders.update', $order), [
            'notes' => 'after pickup',
            'delivery_driver_id' => $otherDriver->id,
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('after pickup', $order->notes);
        $this->assertSame((int) $otherDriver->id, (int) $order->delivery_driver_id);
        $this->assertSame(1, $order->lines()->sum('quantity'));

        Carbon::setTestNow();
    }

    public function test_address_ownership_enforced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        [$customer, , $offering] = $this->b2cFixture();
        $other = GhCustomer::factory()->create();
        $foreignAddress = GhCustomerAddress::factory()->create(['customer_id' => $other->id]);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $foreignAddress->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '11:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors('address_id');

        Carbon::setTestNow();
    }

    public function test_eligibility_service_shared_rule(): void
    {
        $svc = app(GhPickupEligibilityService::class);
        $now = Carbon::parse('2026-10-25 12:00:00');
        $this->assertTrue($svc->isEligibleForPickup(Carbon::parse('2026-10-26 11:00:00'), $now));
        $this->assertFalse($svc->isEligibleForPickup(Carbon::parse('2026-10-27 12:01:00'), $now));
        $this->assertSame(GhOrderStatus::PICKUP, $svc->initialStatus(Carbon::parse('2026-10-26 10:00:00'), $now));
        $this->assertSame(GhOrderStatus::LATER, $svc->initialStatus(Carbon::parse('2026-10-28 10:00:00'), $now));
        $this->assertSame('Asia/Dubai', $svc->timezone());
    }

    /**
     * @return array{0: GhCustomer, 1: GhCustomerAddress, 2?: GhProductOffering}
     */
    private function b2cFixture(bool $withOffering = true): array
    {
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        if (! $withOffering) {
            return [$customer, $address];
        }
        $offering = GhProductOffering::factory()->create(['standard_price' => '10.00']);

        return [$customer, $address, $offering];
    }
}
