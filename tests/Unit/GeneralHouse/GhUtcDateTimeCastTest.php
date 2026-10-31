<?php

namespace Tests\Unit\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GhUtcDateTimeCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_dubai_1400_stores_utc_1000_and_displays_dubai_1400(): void
    {
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => 'Asia/Dubai']);

        $dubai = Carbon::createFromFormat('Y-m-d H:i', '2026-11-05 14:00', 'Asia/Dubai');
        $order = $this->makeOrder([
            'pickup_starts_at' => $dubai,
            'pickup_ends_at' => Carbon::createFromFormat('Y-m-d H:i', '2026-11-05 15:00', 'Asia/Dubai'),
        ]);

        $raw = $order->getAttributes()['pickup_starts_at'];
        $this->assertSame('2026-11-05 10:00:00', $raw);

        $fresh = GhOrder::query()->findOrFail($order->id);
        $this->assertSame('UTC', $fresh->pickup_starts_at->timezoneName);
        $this->assertSame('2026-11-05 10:00:00', $fresh->pickup_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('14:00', $fresh->pickup_starts_at->copy()->timezone('Asia/Dubai')->format('H:i'));
        $this->assertSame('15:00', $fresh->pickup_ends_at->copy()->timezone('Asia/Dubai')->format('H:i'));
    }

    public function test_repeated_read_write_does_not_drift(): void
    {
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => 'Asia/Dubai']);
        $order = $this->makeOrder([
            'pickup_starts_at' => Carbon::createFromFormat('Y-m-d H:i', '2026-11-05 14:00', 'Asia/Dubai'),
            'pickup_ends_at' => Carbon::createFromFormat('Y-m-d H:i', '2026-11-05 15:00', 'Asia/Dubai'),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $fresh = GhOrder::query()->findOrFail($order->id);
            $fresh->pickup_starts_at = $fresh->pickup_starts_at->copy()->timezone('Asia/Dubai');
            $fresh->pickup_ends_at = $fresh->pickup_ends_at->copy()->timezone('Asia/Dubai');
            $fresh->save();
        }

        $final = GhOrder::query()->findOrFail($order->id);
        $this->assertSame('2026-11-05 10:00:00', $final->getAttributes()['pickup_starts_at']);
        $this->assertSame('14:00', $final->pickup_starts_at->timezone('Asia/Dubai')->format('H:i'));
    }

    public function test_cast_round_trip_with_alternate_gh_timezone(): void
    {
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => 'Asia/Riyadh']);
        $local = Carbon::createFromFormat('Y-m-d H:i', '2026-11-05 14:00', 'Asia/Riyadh');
        $order = $this->makeOrder([
            'timezone' => 'Asia/Riyadh',
            'pickup_starts_at' => $local,
            'pickup_ends_at' => $local->copy()->addHour(),
        ]);

        $fresh = GhOrder::query()->findOrFail($order->id);
        $this->assertSame('14:00', $fresh->pickup_starts_at->timezone('Asia/Riyadh')->format('H:i'));
        $this->assertSame($local->utc()->format('Y-m-d H:i:s'), $fresh->getAttributes()['pickup_starts_at']);
    }

    public function test_cast_set_rejects_invalid_type(): void
    {
        $cast = new GhUtcDateTime;
        $this->expectException(\InvalidArgumentException::class);
        $cast->set(new GhOrder, 'pickup_starts_at', ['nope'], []);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): GhOrder
    {
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $manager = User::factory()->manager()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => config('general_house.timezone', 'Asia/Dubai'),
            'created_by' => $manager->id,
        ], $overrides));
    }
}
