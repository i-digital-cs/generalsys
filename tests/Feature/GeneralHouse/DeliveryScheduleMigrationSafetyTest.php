<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Production upgrade safety for scheduled_delivery_at migrations.
 * Uses in-memory sqlite via RefreshDatabase — never touches TEST/LIVE MySQL.
 */
class DeliveryScheduleMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fresh_migrate_adds_nullable_column_without_synthetic_backfill(): void
    {
        $this->assertTrue(Schema::hasColumn('gh_orders', 'scheduled_delivery_at'));

        $tz = 'Asia/Dubai';
        $pickup = Carbon::parse('2026-11-10 10:00:00', $tz)->utc();
        $order = GhOrder::factory()->create([
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => $pickup,
            'pickup_ends_at' => $pickup->copy()->addHour(),
            'scheduled_delivery_at' => null,
            'picked_up_at' => null,
        ]);

        $this->assertNull($order->fresh()->scheduled_delivery_at);
        $this->assertNull(
            DB::table('gh_orders')->where('id', $order->id)->value('scheduled_delivery_at')
        );
    }

    public function test_clear_migration_removes_only_synthetic_pickup_day_0800(): void
    {
        $tz = 'Asia/Dubai';
        $pickup = Carbon::parse('2026-11-10 15:30:00', $tz)->utc();
        $synthetic = Carbon::parse('2026-11-10 08:00:00', $tz)->utc();
        $legitimate = Carbon::parse('2026-11-12 14:00:00', $tz)->utc();

        $legacy = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => $pickup,
            'pickup_ends_at' => $pickup->copy()->addHour(),
            'scheduled_delivery_at' => null,
            'picked_up_at' => $pickup,
        ]);

        $syntheticOrder = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => $pickup,
            'pickup_ends_at' => $pickup->copy()->addHour(),
            'scheduled_delivery_at' => $synthetic,
            'picked_up_at' => $pickup,
        ]);

        $legitOrder = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => $pickup,
            'pickup_ends_at' => $pickup->copy()->addHour(),
            'scheduled_delivery_at' => $legitimate,
            'picked_up_at' => $pickup,
        ]);

        $this->runClearAssumedMigration();

        $this->assertNull($legacy->fresh()->scheduled_delivery_at);
        $this->assertNull($syntheticOrder->fresh()->scheduled_delivery_at);
        $this->assertNotNull($legitOrder->fresh()->scheduled_delivery_at);
        $this->assertTrue(
            $legitOrder->fresh()->scheduled_delivery_at->equalTo($legitimate)
        );
    }

    public function test_clear_migration_is_idempotent_and_preserves_legitimate_on_rerun(): void
    {
        $tz = 'Asia/Dubai';
        $pickup = Carbon::parse('2026-11-11 09:00:00', $tz)->utc();
        $legitimate = Carbon::parse('2026-11-11 16:00:00', $tz)->utc();

        $order = GhOrder::factory()->create([
            'pickup_starts_at' => $pickup,
            'pickup_ends_at' => $pickup->copy()->addHour(),
            'scheduled_delivery_at' => $legitimate,
        ]);

        $migration = $this->loadClearAssumedMigration();
        $migration->up();
        $migration->up();

        $this->assertTrue($order->fresh()->scheduled_delivery_at->equalTo($legitimate));
    }

    public function test_partial_state_old_backfill_then_safe_clear_preserves_real_appointments(): void
    {
        $tz = 'Asia/Dubai';
        // Simulate post-old-135901 / pre-160000: every row had pickup-day 08:00,
        // then a manager set a real appointment on one order.
        $pickupA = Carbon::parse('2026-11-08 11:00:00', $tz)->utc();
        $pickupB = Carbon::parse('2026-11-09 13:00:00', $tz)->utc();
        $syntheticA = Carbon::parse('2026-11-08 08:00:00', $tz)->utc();
        $syntheticB = Carbon::parse('2026-11-09 08:00:00', $tz)->utc();
        $managerSet = Carbon::parse('2026-11-10 17:30:00', $tz)->utc();

        $assumed = GhOrder::factory()->create([
            'pickup_starts_at' => $pickupA,
            'pickup_ends_at' => $pickupA->copy()->addHour(),
            'scheduled_delivery_at' => $syntheticA,
        ]);
        $corrected = GhOrder::factory()->create([
            'pickup_starts_at' => $pickupB,
            'pickup_ends_at' => $pickupB->copy()->addHour(),
            'scheduled_delivery_at' => $managerSet,
        ]);
        // Still shows synthetic for B's pickup day in a third row:
        $stillAssumed = GhOrder::factory()->create([
            'pickup_starts_at' => $pickupB,
            'pickup_ends_at' => $pickupB->copy()->addHour(),
            'scheduled_delivery_at' => $syntheticB,
        ]);

        $this->runClearAssumedMigration();

        $this->assertNull($assumed->fresh()->scheduled_delivery_at);
        $this->assertNull($stillAssumed->fresh()->scheduled_delivery_at);
        $this->assertTrue($corrected->fresh()->scheduled_delivery_at->equalTo($managerSet));
    }

    public function test_new_order_create_still_requires_delivery_schedule_while_column_nullable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', 'Asia/Dubai'));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create(['min_order_amount' => '0.00']);
        $customer = GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create([
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '10.00',
        ]);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-17 14:00',
            'pickup_driver_id' => $driver->id,
        ], $manager);

        $this->assertNotNull($order->scheduled_delivery_at);
        $this->assertSame(
            '2026-11-17 14:00',
            $order->scheduled_delivery_at->timezone('Asia/Dubai')->format('Y-m-d H:i')
        );
    }

    public function test_add_column_migration_is_idempotent_when_column_already_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('gh_orders', 'scheduled_delivery_at'));
        $before = DB::table('gh_orders')->count();

        $this->loadAddColumnMigration()->up();

        $this->assertTrue(Schema::hasColumn('gh_orders', 'scheduled_delivery_at'));
        $this->assertSame($before, DB::table('gh_orders')->count());
    }

    private function runClearAssumedMigration(): void
    {
        $this->loadClearAssumedMigration()->up();
    }

    private function loadClearAssumedMigration(): object
    {
        $path = database_path('migrations/2026_11_15_160000_clear_assumed_same_day_scheduled_delivery_at.php');

        return $this->instantiateMigrationFromFile($path);
    }

    private function loadAddColumnMigration(): object
    {
        $path = database_path('migrations/2026_11_15_135901_add_scheduled_delivery_at_to_gh_orders_table.php');

        return $this->instantiateMigrationFromFile($path);
    }

    private function instantiateMigrationFromFile(string $path): object
    {
        // Plain require() returns true on a second include of the same path.
        $tmp = tempnam(sys_get_temp_dir(), 'gh_mig_');
        if ($tmp === false) {
            $this->fail('Unable to allocate temp migration path');
        }
        $tmpPhp = $tmp.'.php';
        @unlink($tmp);
        if (! copy($path, $tmpPhp)) {
            $this->fail('Unable to copy migration for isolated require: '.$path);
        }

        try {
            $migration = require $tmpPhp;
        } finally {
            @unlink($tmpPhp);
        }

        if (! is_object($migration)) {
            $this->fail('Migration file did not return an object: '.$path);
        }

        return $migration;
    }
}
