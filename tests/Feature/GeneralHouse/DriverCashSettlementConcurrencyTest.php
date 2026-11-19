<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use Dotenv\Dotenv;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\GeneralHouse\Concerns\GhDriverCashFixtures;
use Tests\TestCase;

/**
 * Parallel managers settling / reversing the same driver cash.
 *
 * SQLite variant: real forked processes on a scratch file DB. SQLite has no row locks, so a
 * write-lock gate serializes workers; it proves every worker re-reads locked state inside its
 * own transaction (caller models stale). It is NOT proof of MySQL/InnoDB row-lock behaviour.
 *
 * MySQL variant: same scenarios with real FOR UPDATE locks. Runs only when an isolated, disposable
 * database is configured (MYSQL_TEST_DATABASE + MYSQL_TEST_DISPOSABLE=1); skipped otherwise.
 */
#[Group('concurrency')]
class DriverCashSettlementConcurrencyTest extends TestCase
{
    use GhDriverCashFixtures;

    private const WORKERS = 4;

    private const TEST_APP_DATABASE = 'd14098_general';

    private const DISPOSABLE_MARKER = 'gh_disposable_concurrency_marker';

    private string $connection;

    private bool $gate = false;

    private ?string $databasePath = null;

    private string $outcomeDir;

    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pcntl') || ! extension_loaded('posix')) {
            $this->markTestSkipped('pcntl + posix are required for parallel settlement tests.');
        }
        $this->previousDefault = (string) config('database.default');
        $this->outcomeDir = (string) tempnam(sys_get_temp_dir(), 'gh-dcs-out-');
        unlink($this->outcomeDir);
        mkdir($this->outcomeDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            DB::disconnect($this->connection);
        }
        config(['database.default' => $this->previousDefault]);
        array_map('unlink', glob($this->outcomeDir.'/*') ?: []);
        @rmdir($this->outcomeDir);
        if ($this->databasePath !== null) {
            array_map('unlink', glob($this->databasePath.'*') ?: []);
        }
        parent::tearDown();
    }

    public function test_sqlite_parallel_full_settlements_of_one_invoice_settle_once(): void
    {
        $this->bootSqlite();
        $this->assertFullSettlementRace();
    }

    public function test_sqlite_parallel_partial_settlements_never_overdraw(): void
    {
        $this->bootSqlite();
        $this->assertPartialSettlementRace();
    }

    public function test_sqlite_parallel_reversals_reverse_once(): void
    {
        $this->bootSqlite();
        $this->assertReversalRace();
    }

    public function test_sqlite_parallel_same_idempotency_key_creates_one_settlement(): void
    {
        $this->bootSqlite();
        $this->assertSameKeyRace();
    }

    #[Group('mysql-concurrency')]
    public function test_mysql_parallel_full_settlements_of_one_invoice_settle_once(): void
    {
        $this->bootMysql();
        $this->assertFullSettlementRace();
    }

    #[Group('mysql-concurrency')]
    public function test_mysql_parallel_partial_settlements_never_overdraw(): void
    {
        $this->bootMysql();
        $this->assertPartialSettlementRace();
    }

    #[Group('mysql-concurrency')]
    public function test_mysql_parallel_reversals_reverse_once(): void
    {
        $this->bootMysql();
        $this->assertReversalRace();
    }

    #[Group('mysql-concurrency')]
    public function test_mysql_parallel_same_idempotency_key_creates_one_settlement(): void
    {
        $this->bootMysql();
        $this->assertSameKeyRace();
    }

    private function assertFullSettlementRace(): void
    {
        [$driver, $collection] = $this->driverWithCollection('100.00');
        $outcomes = $this->race(fn (int $i, User $manager) => $this->settle($driver, $manager, $collection, '100.00', 'full-'.$i));

        $this->assertSame(['rejected:received_amount', 'rejected:received_amount', 'rejected:received_amount', 'settled'], $outcomes);
        $this->assertSame(1, GhDriverCashSettlement::query()->count());
        $this->assertSame(1, GhDriverCashSettlementAllocation::query()->count());
        $this->assertBalance($driver, '0.00');
    }

    private function assertPartialSettlementRace(): void
    {
        [$driver, $collection] = $this->driverWithCollection('100.00');
        $outcomes = $this->race(fn (int $i, User $manager) => $this->settle($driver, $manager, $collection, '30.00', 'part-'.$i));

        $this->assertSame(['rejected:received_amount', 'settled', 'settled', 'settled'], $outcomes);
        $this->assertSame('90.00', number_format((float) GhDriverCashSettlementAllocation::query()->sum('amount'), 2, '.', ''));
        $this->assertBalance($driver, '10.00');
    }

    private function assertReversalRace(): void
    {
        [$driver, $collection] = $this->driverWithCollection('100.00');
        $settlement = app(GhDriverCashSettlementService::class)->settle($driver, User::factory()->manager()->create(), $this->settlePayload('100.00', [$collection->id => '100.00']));
        $outcomes = $this->race(function (int $i, User $manager) use ($settlement) {
            app(GhDriverCashSettlementService::class)->reverse(GhDriverCashSettlement::query()->findOrFail($settlement->id), $manager, [
                'reason' => 'parallel '.$i, 'idempotency_key' => 'rev-'.$i,
            ]);

            return 'reversed';
        });

        $this->assertSame(['rejected:reason', 'rejected:reason', 'rejected:reason', 'reversed'], $outcomes);
        $this->assertSame(1, GhDriverCashSettlementReversal::query()->count());
        $this->assertBalance($driver, '100.00');
    }

    private function assertSameKeyRace(): void
    {
        [$driver, $collection] = $this->driverWithCollection('100.00');
        $outcomes = $this->race(fn (int $i, User $manager) => $this->settle($driver, $manager, $collection, '40.00', 'shared-key'));

        $this->assertSame(array_fill(0, self::WORKERS, 'settled'), $outcomes);
        $this->assertSame(1, GhDriverCashSettlement::query()->count());
        $this->assertBalance($driver, '60.00');
    }

    private function settle(User $driver, User $manager, GhDriverCashCollection $collection, string $amount, string $key): string
    {
        app(GhDriverCashSettlementService::class)->settle($driver, $manager, $this->settlePayload($amount, [$collection->id => $amount], $key));

        return 'settled';
    }

    /**
     * @param  callable(int, User): string  $operation
     * @return list<string> sorted outcomes
     */
    private function race(callable $operation): array
    {
        $managerIds = User::factory()->manager()->count(self::WORKERS)->create()->pluck('id')->all();
        DB::disconnect($this->connection);
        $startAt = microtime(true) + 1.0;
        $pids = [];
        foreach ($managerIds as $i => $managerId) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                $this->runWorker($i, (int) $managerId, $startAt, $operation);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect($this->connection);

        $outcomes = array_map(fn (string $f) => trim((string) file_get_contents($f)), glob($this->outcomeDir.'/*') ?: []);
        $this->assertCount(self::WORKERS, $outcomes, 'every worker reported: '.json_encode($outcomes));
        sort($outcomes);

        return $outcomes;
    }

    private function runWorker(int $index, int $managerId, float $startAt, callable $operation): never
    {
        $outcome = 'error';
        try {
            DB::purge($this->connection);
            $manager = User::query()->findOrFail($managerId);
            usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));
            if ($this->gate) {
                DB::beginTransaction();
                DB::table('race_gate')->where('id', 1)->increment('hits');
            }
            try {
                $outcome = $operation($index, $manager);
                if ($this->gate) {
                    DB::commit();
                }
            } catch (\Throwable $e) {
                if ($this->gate) {
                    DB::rollBack();
                }
                throw $e;
            }
        } catch (ValidationException $e) {
            $outcome = 'rejected:'.implode(',', array_keys($e->errors()));
        } catch (\PDOException|QueryException $e) {
            $outcome = 'db-error:'.$e->getMessage();
        } catch (\Throwable $e) {
            $outcome = 'error:'.$e->getMessage();
        }
        file_put_contents($this->outcomeDir.'/'.$index, $outcome);
        posix_kill(posix_getpid(), SIGKILL);
        exit(1);
    }

    /** @return array{0: User, 1: GhDriverCashCollection} */
    private function driverWithCollection(string $amount): array
    {
        $driver = User::factory()->driver()->create();

        return [$driver, $this->collectCash($driver, $amount)];
    }

    private function assertBalance(User $driver, string $expected): void
    {
        $balance = GhDriverCashBalance::query()->findOrFail($driver->id);
        $this->assertSame($expected, (string) $balance->outstanding);
        $this->assertGreaterThanOrEqual(0, (float) $balance->outstanding);
    }

    private function bootSqlite(): void
    {
        $this->connection = 'gh_dcs_race';
        $this->gate = true;
        $this->databasePath = (string) tempnam(sys_get_temp_dir(), 'gh-dcs-race-');
        config([
            'database.connections.gh_dcs_race' => array_merge(config('database.connections.sqlite'), [
                'database' => $this->databasePath,
                'busy_timeout' => 10000,
                'journal_mode' => 'wal',
                'foreign_key_constraints' => true,
            ]),
            'database.default' => 'gh_dcs_race',
        ]);
        Artisan::call('migrate', ['--database' => 'gh_dcs_race', '--force' => true]);
        DB::statement('CREATE TABLE race_gate (id INTEGER PRIMARY KEY, hits INTEGER NOT NULL)');
        DB::table('race_gate')->insert(['id' => 1, 'hits' => 0]);
        $this->setUpDriverCash();
    }

    public function test_mysql_target_guard_refuses_application_database(): void
    {
        $this->assertNotNull($this->refusalFor('d14098_general'));
        $this->assertNotNull($this->refusalFor('D14098_GENERAL'));
        $this->assertNotNull($this->refusalFor($this->appDatabaseFromEnvFile() ?: self::TEST_APP_DATABASE));
        $this->assertNotNull($this->refusalFor(''));
        $this->assertNull($this->refusalFor('u123_gh_concurrency_scratch'));
    }

    /** Name-only checks; runs before any connection to the target is opened. */
    private function refusalFor(string $database): ?string
    {
        foreach (array_filter([self::TEST_APP_DATABASE, $this->appDatabaseFromEnvFile()]) as $forbidden) {
            if (strcasecmp($database, $forbidden) === 0) {
                return "MYSQL_TEST_DATABASE must not be the application database ({$forbidden}).";
            }
        }

        return $database === '' ? 'MYSQL_TEST_DATABASE is empty.' : null;
    }

    /** phpunit.xml overrides DB_DATABASE (:memory:), so the real application database is read from the file. */
    private function appDatabaseFromEnvFile(): string
    {
        $path = base_path('.env');

        return is_file($path) ? (string) (Dotenv::parse((string) file_get_contents($path))['DB_DATABASE'] ?? '') : '';
    }

    private function bootMysql(): void
    {
        $database = (string) env('MYSQL_TEST_DATABASE', '');
        if ($database === '' || env('MYSQL_TEST_DISPOSABLE') !== '1') {
            $this->markTestSkipped('Isolated disposable MYSQL_TEST_DATABASE (MYSQL_TEST_DISPOSABLE=1) not configured; MySQL concurrency proof pending.');
        }
        if (! extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql is required for the MySQL concurrency tests.');
        }
        $refusal = $this->refusalFor($database);
        if ($refusal !== null) {
            $this->fail($refusal);
        }
        $this->connection = 'gh_dcs_mysql';
        config([
            'database.connections.gh_dcs_mysql' => array_merge(config('database.connections.mysql'), [
                'host' => env('MYSQL_TEST_HOST', config('database.connections.mysql.host')),
                'port' => env('MYSQL_TEST_PORT', config('database.connections.mysql.port')),
                'database' => $database,
                'username' => env('MYSQL_TEST_USERNAME', config('database.connections.mysql.username')),
                'password' => env('MYSQL_TEST_PASSWORD', config('database.connections.mysql.password')),
            ]),
            'database.default' => 'gh_dcs_mysql',
        ]);
        $tables = array_map(fn ($row) => strtolower((string) $row->t), DB::connection('gh_dcs_mysql')->select(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?', [$database]
        ));
        if ($tables !== [] && ! in_array(self::DISPOSABLE_MARKER, $tables, true)) {
            $this->fail("MYSQL_TEST_DATABASE {$database} already has tables and no ".self::DISPOSABLE_MARKER.' marker; refusing to wipe it.');
        }
        Artisan::call('migrate:fresh', ['--database' => 'gh_dcs_mysql', '--force' => true]);
        DB::statement('CREATE TABLE '.self::DISPOSABLE_MARKER.' (id INT PRIMARY KEY)');
        $this->setUpDriverCash();
    }
}
