<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderDeletionService;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real parallel processes against a shared file database (the in-memory test DB
 * cannot be shared across forks). Never points at a business database.
 */
#[Group('concurrency')]
class AdminOrderDeletionConcurrencyTest extends TestCase
{
    private const WORKERS = 4;

    private string $databasePath;

    private string $outcomeDir;

    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pcntl') || ! extension_loaded('posix')) {
            $this->markTestSkipped('pcntl + posix are required for the parallel deletion test.');
        }

        $this->databasePath = (string) tempnam(sys_get_temp_dir(), 'gh-del-race-');
        $this->outcomeDir = $this->databasePath.'.outcomes';
        mkdir($this->outcomeDir);

        $this->previousDefault = (string) config('database.default');
        config([
            'database.connections.gh_deletion_race' => array_merge(config('database.connections.sqlite'), [
                'database' => $this->databasePath,
                'busy_timeout' => 5000,
                'journal_mode' => 'wal',
                'foreign_key_constraints' => true,
            ]),
            'database.default' => 'gh_deletion_race',
        ]);
        Artisan::call('migrate', ['--database' => 'gh_deletion_race', '--force' => true]);
        DB::statement('CREATE TABLE race_gate (id INTEGER PRIMARY KEY, hits INTEGER NOT NULL)');
        DB::table('race_gate')->insert(['id' => 1, 'hits' => 0]);
    }

    protected function tearDown(): void
    {
        if (isset($this->previousDefault)) {
            DB::disconnect('gh_deletion_race');
            config(['database.default' => $this->previousDefault]);
            array_map('unlink', glob($this->outcomeDir.'/*') ?: []);
            @rmdir($this->outcomeDir);
            array_map('unlink', glob($this->databasePath.'*') ?: []);
        }
        parent::tearDown();
    }

    public function test_parallel_deletes_of_one_order_produce_exactly_one_deletion_and_one_audit_event(): void
    {
        $order = $this->makeLaterOrder();
        $managerIds = User::factory()->manager()->count(self::WORKERS)->create()->pluck('id')->all();
        DB::disconnect('gh_deletion_race');
        $startAt = microtime(true) + 1.0;

        $pids = [];
        foreach ($managerIds as $i => $managerId) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                $this->runWorker($i, (int) $order->id, (int) $managerId, $startAt);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect('gh_deletion_race');

        $outcomes = array_map(fn (string $f) => trim((string) file_get_contents($f)), glob($this->outcomeDir.'/*') ?: []);
        $this->assertCount(self::WORKERS, $outcomes, 'every worker reported an outcome');
        sort($outcomes);
        $this->assertSame(
            array_merge(['deleted'], array_fill(0, self::WORKERS - 1, 'rejected:already_deleted')),
            $outcomes,
        );

        $row = GhOrder::withTrashed()->findOrFail($order->id);
        $this->assertNotNull($row->deleted_at);
        $this->assertContains((int) $row->deleted_by, array_map('intval', $managerIds));
        $events = GhOrderEvent::query()->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::ORDER_DELETED)->get();
        $this->assertCount(1, $events);
        $this->assertSame((int) $row->deleted_by, (int) $events->first()->actor_id);
        $this->assertSame($row->delete_reason, $events->first()->payload['reason']);
    }

    private function runWorker(int $index, int $orderId, int $managerId, float $startAt): never
    {
        $outcome = 'error';
        try {
            DB::purge('gh_deletion_race');
            $order = GhOrder::query()->findOrFail($orderId);
            $manager = User::query()->findOrFail($managerId);
            $deletions = app(GhOrderDeletionService::class);
            usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));
            // SQLite has no row locks: taking its write lock first queues the workers the way
            // InnoDB queues them on the order's FOR UPDATE, so each later worker runs only
            // after the previous one committed, with the caller's model already stale.
            DB::beginTransaction();
            DB::table('race_gate')->where('id', 1)->increment('hits');
            try {
                $deletions->delete($order, $manager, 'Parallel worker '.$index);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            $outcome = 'deleted';
        } catch (ValidationException $e) {
            $outcome = ($e->errors()['order'][0] ?? '') === __('gh.errors.order_delete_already_deleted')
                ? 'rejected:already_deleted'
                : 'rejected:'.json_encode($e->errors());
        } catch (\PDOException|QueryException $e) {
            $outcome = str_contains($e->getMessage(), 'database is locked') ? 'busy' : 'error:'.$e->getMessage();
        } catch (\Throwable $e) {
            $outcome = 'error:'.$e->getMessage();
        }
        file_put_contents($this->outcomeDir.'/'.$index, $outcome);
        posix_kill(posix_getpid(), SIGKILL);
        exit(1);
    }

    private function makeLaterOrder(): GhOrder
    {
        DB::table('gh_sequences')->insertOrIgnore(['name' => 'orders', 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create();
        $address = GhCustomerAddress::factory()->create(['customer_id' => $customer->id, 'zone_id' => $zone->id]);

        return GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'status' => GhOrderStatus::LATER,
            'picked_up_at' => null,
            'pickup_confirmed_by' => null,
            'created_by' => User::factory()->manager()->create()->id,
        ]);
    }
}
