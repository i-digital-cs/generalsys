<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Support\GeneralHouse\GhDateTimePresenter;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GhUaeTimezonePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => 'Asia/Dubai']);
        Carbon::setTestNow(Carbon::parse('2026-11-19 08:00:00', 'Asia/Dubai'));
        foreach (['orders', 'invoices', 'payments'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_order_show_renders_dubai_twelve_hour_and_uae_badge_in_english(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->seedOrder(['pickup_driver_id' => User::factory()->driver()->create()->id]);

        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.show', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('10:00 AM', $html);
        $this->assertStringContainsString('🇦🇪 UAE', $html);
        $this->assertSame('Africa/Cairo', (string) config('app.timezone'));
    }

    public function test_order_form_shows_uae_selection_notice_in_arabic(): void
    {
        $manager = User::factory()->manager()->create();

        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('🇦🇪 الإمارات', $html);
        $this->assertStringContainsString('دبي', $html);
        $this->assertStringContainsString('Asia/Dubai', $html);
        $this->assertStringContainsString(GhDateTimePresenter::selectionNotice(), $html);
    }

    public function test_board_card_uses_twelve_hour_dubai_time(): void
    {
        $manager = User::factory()->manager()->create();
        $this->seedOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => User::factory()->driver()->create()->id,
            'created_by' => $manager->id,
        ]);

        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/\d{1,2}:\d{2}\s(AM|PM)/', $html);
        $this->assertStringContainsString('gh-uae-badge--board', $html);
        $this->assertStringContainsString('UAE Time', $html);
        $this->assertStringContainsString('🇦🇪', $html);
        $this->assertSame(1, preg_match_all('/gh-uae-badge--board/', $html));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedOrder(array $overrides = []): GhOrder
    {
        $tz = 'Asia/Dubai';
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create();
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::LATER,
            'pickup_starts_at' => Carbon::parse('2026-11-19 10:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-19 11:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-20 10:00:00', $tz)->utc(),
        ], $overrides));
    }
}
