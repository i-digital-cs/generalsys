<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Orders\GhPickupEligibilityService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledPickupTest extends TestCase
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

    public function test_create_with_single_scheduled_datetime_derives_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 08:00:00', 'Asia/Dubai'));
        [$manager, $driver, $customer, $address, $offering] = $this->fixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-03T15:30',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $order = GhOrder::query()->firstOrFail();
        $this->assertSame('15:30', $order->pickup_starts_at->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame('16:30', $order->pickup_ends_at->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame(GhOrderStatus::LATER, $order->status);
        Carbon::setTestNow();
    }

    public function test_missing_end_time_is_not_a_validation_error(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 08:00:00', 'Asia/Dubai'));
        [$manager, $driver, $customer, $address, $offering] = $this->fixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-02T10:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionDoesntHaveErrors(['pickup_window_end', 'scheduled_pickup_at']);
        Carbon::setTestNow();
    }

    public function test_booking_48h_ahead_is_later_23h_is_pickup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Asia/Dubai'));
        [$manager, $driver, $customer, $address, $offering] = $this->fixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-07T12:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::LATER, GhOrder::query()->latest('id')->firstOrFail()->status);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-06T11:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::PICKUP, GhOrder::query()->latest('id')->firstOrFail()->status);
        Carbon::setTestNow();
    }

    public function test_overdue_unfinished_pickup_remains_eligible(): void
    {
        $elig = app(GhPickupEligibilityService::class);
        $pickup = Carbon::parse('2026-11-01 10:00:00', 'Asia/Dubai');
        $now = Carbon::parse('2026-11-02 12:00:00', 'Asia/Dubai');
        $this->assertTrue($elig->isEligibleForPickup($pickup, $now));
    }

    public function test_reschedule_via_scheduled_datetime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', 'UTC'));
        [$manager, $driver, $customer, $address, $offering] = $this->fixture();
        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-10 10:00',
            'pickup_driver_id' => $driver->id,
        ], $manager);

        $window = app(GhOrderService::class)->parsePickupWindow([
            'scheduled_pickup_at' => '2026-11-12T14:00',
        ]);
        app(GhOrderStateService::class)->reschedulePickup($order, $manager, $window);

        $fresh = $order->fresh();
        $this->assertSame('14:00', $fresh->pickup_starts_at->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame('15:00', $fresh->pickup_ends_at->timezone('Asia/Dubai')->format('H:i'));
        Carbon::setTestNow();
    }

    public function test_legacy_start_end_window_still_parses(): void
    {
        $window = app(GhOrderService::class)->parsePickupWindow([
            'pickup_date' => '2026-11-05',
            'pickup_window_start' => '09:00',
            'pickup_window_end' => '11:00',
        ]);
        $this->assertSame('09:00', $window['starts_at']->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame('11:00', $window['ends_at']->timezone('Asia/Dubai')->format('H:i'));
    }

    public function test_order_form_shows_scheduled_pickup_label_en_ar(): void
    {
        $manager = User::factory()->manager()->create();
        $en = $this->actingAs($manager)->withSession(['locale' => 'en'])
            ->get(route('gh.orders.create'))->assertOk()->getContent();
        $this->assertStringContainsString('Scheduled Pickup', $en);
        $this->assertStringNotContainsString('name="pickup_window_end"', $en);

        $ar = $this->actingAs($manager)->withSession(['locale' => 'ar'])
            ->get(route('gh.orders.create'))->assertOk()->getContent();
        $this->assertStringContainsString('موعد الاستلام', $ar);
    }

    /** @return array{0: User, 1: User, 2: GhCustomer, 3: GhCustomerAddress, 4: GhProductOffering} */
    private function fixture(): array
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '10.00']);

        return [$manager, $driver, $customer, $address, $offering];
    }
}
