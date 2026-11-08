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

class TimezoneIntegrityTest extends TestCase
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

    public function test_parse_and_create_preserves_dubai_wall_clock_on_display(): void
    {
        $svc = app(GhOrderService::class);
        $window = $svc->parsePickupWindow([
            'pickup_date' => '2026-11-05',
            'pickup_window_start' => '14:00',
            'pickup_window_end' => '15:00',
        ]);
        $this->assertSame('Asia/Dubai', $window['timezone']);
        $this->assertSame('2026-11-05 10:00:00', $window['starts_at']->copy()->utc()->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00:00', 'UTC'));
        $order = $this->createViaService('14:00', '15:00', '2026-11-05');
        Carbon::setTestNow();

        $this->assertSame('2026-11-05 10:00:00', $order->getAttributes()['pickup_starts_at']);
        $this->assertSame('14:00', $order->pickup_starts_at->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame('15:00', $order->pickup_ends_at->timezone('Asia/Dubai')->format('H:i'));

        $manager = User::factory()->manager()->create();
        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.show', $order))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('14:00', $html);
        // End time is derived for schema/compat only — show page uses scheduled start.
        $this->assertStringContainsString(__('gh.orders.scheduled_pickup'), $html);
    }

    public function test_eligibility_exact_24h_and_one_second_edges(): void
    {
        $elig = app(GhPickupEligibilityService::class);
        $tz = 'Asia/Dubai';
        $pickup = Carbon::parse('2026-11-01 14:00:00', $tz);
        $exact = $pickup->copy()->subHours(24);
        $before = $exact->copy()->subSecond();
        $after = $exact->copy()->addSecond();

        $this->assertFalse($elig->isEligibleForPickup($pickup, $before));
        $this->assertTrue($elig->isEligibleForPickup($pickup, $exact));
        $this->assertTrue($elig->isEligibleForPickup($pickup, $after));
    }

    public function test_scheduler_uses_utc_sql_cutoff_and_promotes_correctly(): void
    {
        // Create while still outside the 24h window.
        Carbon::setTestNow(Carbon::parse('2026-10-30 09:00:00', 'UTC'));
        $order = $this->createViaService('14:00', '15:00', '2026-11-01');
        $this->assertSame(GhOrderStatus::LATER, $order->status);

        // Exactly 24h before Dubai 14:00 = 2026-10-31 10:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', 'UTC'));
        $n = app(GhOrderStateService::class)->promoteEligibleLaterOrders();
        $this->assertSame(1, $n);
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);

        // One second earlier would not promote (re-create edge via eligibility service).
        $elig = app(GhPickupEligibilityService::class);
        $pickup = Carbon::parse('2026-11-01 14:00:00', 'Asia/Dubai');
        $this->assertFalse($elig->isEligibleForPickup($pickup, Carbon::parse('2026-10-31 09:59:59', 'UTC')));
        Carbon::setTestNow();
    }

    public function test_reschedule_keeps_dubai_wall_clock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', 'UTC'));
        $order = $this->createViaService('10:00', '11:00', '2026-11-10');
        $manager = User::where('role', 'MANAGER')->first() ?? User::factory()->manager()->create();

        app(GhOrderStateService::class)->reschedulePickup($order, $manager, app(GhOrderService::class)->parsePickupWindow([
            'pickup_date' => '2026-11-12',
            'pickup_window_start' => '14:00',
            'pickup_window_end' => '15:00',
        ]));

        $fresh = $order->fresh();
        $this->assertSame('14:00', $fresh->pickup_starts_at->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame('2026-11-12 10:00:00', $fresh->getAttributes()['pickup_starts_at']);
        Carbon::setTestNow();
    }

    public function test_arabic_show_also_renders_dubai_1400(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', 'UTC'));
        $order = $this->createViaService('14:00', '15:00', '2026-11-05');
        $manager = User::factory()->manager()->create();
        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.orders.show', $order))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('14:00', $html);
        $this->assertStringContainsString(__('gh.orders.scheduled_pickup'), $html);
        Carbon::setTestNow();
    }

    private function createViaService(string $start, string $end, string $date): GhOrder
    {
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '25.00']);
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();

        return app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => $date,
            'pickup_window_start' => $start,
            'pickup_window_end' => $end,
            'pickup_driver_id' => $driver->id,
        ], $manager);
    }
}
