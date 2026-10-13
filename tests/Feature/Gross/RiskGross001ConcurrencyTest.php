<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Task 3.1 — first-write race hardening for RISK-GROSS-001.
 *
 * Confirmed legacy race (pre-hardening) when ON_HAND >= 2P:
 *
 *   T1 BEGIN
 *   T2 BEGIN
 *   T1 SELECT repair SALE_OUT FOR UPDATE → empty (locks nothing)
 *   T2 SELECT repair SALE_OUT FOR UPDATE → empty (locks nothing)
 *   T1 lock balance; ON_HAND-=P; WITH_COURIER+=P; INSERT SALE_OUT; COMMIT
 *   T2 lock balance; ON_HAND-=P; WITH_COURIER+=P; INSERT SALE_OUT #2; COMMIT
 *
 * Mitigation (no schema): lock allocation (when ref is allocation) → lock balance →
 * recheck repair SALE_OUT → transfer. Empty movement FOR UPDATE alone is insufficient.
 *
 * MySQL parallel fork verification requires an isolated MYSQL_TEST_* database
 * (not shared business data). Without it this suite stays on SQLite logic tests.
 */
class RiskGross001ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private GrossItem $item;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->manager = User::factory()->manager()->create();
        $this->item = app(ItemService::class)->create([
            'name' => 'Risk001 Concurrency',
            'pieces_per_carton' => 1,
            'reorder_level' => 0,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
    }

    private function balance(): GrossStockBalance
    {
        return GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
    }

    private function totalTracked(): int
    {
        $b = $this->balance();

        return (int) $b->qty_on_hand_pieces
            + (int) $b->qty_with_courier_pieces
            + (int) $b->qty_lost_pieces;
    }

    private function makeAllocation(int $pending): GrossShipmentAllocation
    {
        $line = GrossSalesInvoiceLine::query()->create([
            'sales_invoice_id' => GrossSalesInvoice::query()->create([
                'invoice_number' => 'MH-CONC-'.uniqid(),
                'status' => GrossSalesInvoice::STATUS_REFUSED,
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_WITH_COURIER,
                'currency' => 'EGP',
                'customer_name' => 'C',
                'customer_phone' => '010',
                'subtotal' => 0,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total' => 0,
                'net_merchandise' => 0,
                'created_by' => $this->manager->id,
            ])->id,
            'item_id' => $this->item->id,
            'item_code_snapshot' => 'C',
            'item_name_snapshot' => 'C',
            'qty' => $pending,
            'unit' => 'PIECE',
            'conversion_factor_snapshot' => 1,
            'qty_pieces' => $pending,
            'unit_price' => 1,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'line_total' => $pending,
            'returned_amount' => 0,
            'lost_amount' => 0,
            'net_line_total' => $pending,
        ]);

        return GrossShipmentAllocation::query()->create([
            'sales_invoice_line_id' => $line->id,
            'conversion_factor_snapshot' => 1,
            'qty_shipped_pieces' => $pending,
            'qty_delivered_pieces' => 0,
            'qty_returned_pieces' => $pending,
            'qty_returned_received_pieces' => $pending,
            'qty_with_courier_pieces' => 0,
            'qty_lost_pieces' => 0,
        ]);
    }

    public function test_duplicate_reference_detection_after_locking_returns_same_movement(): void
    {
        $this->stock->adjustIn($this->item, 20, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(4);

        $first = $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            4,
            GrossShipmentAllocation::class,
            (int) $allocation->id,
            $this->manager,
        );
        $second = $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            4,
            GrossShipmentAllocation::class,
            (int) $allocation->id,
            $this->manager,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(16, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(4, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(20, $this->totalTracked());
        $this->assertSame(
            1,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->where('ref_id', $allocation->id)
                ->count(),
        );
    }

    public function test_retry_after_successful_repair_is_idempotent(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(3);

        $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            3,
            GrossShipmentAllocation::class,
            (int) $allocation->id,
        );
        $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            3,
            GrossShipmentAllocation::class,
            (int) $allocation->id,
        );

        $this->assertSame(7, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(3, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(10, $this->totalTracked());
    }

    public function test_retry_after_failed_transaction_leaves_no_partial_writes_then_succeeds(): void
    {
        $this->stock->adjustIn($this->item, 8, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(3);
        $before = $this->balance()->fresh();

        $failOnce = true;
        GrossStockMovement::creating(function () use (&$failOnce) {
            if ($failOnce) {
                $failOnce = false;
                throw new \RuntimeException('forced ledger failure');
            }
        });

        try {
            $this->stock->repairMoveOnHandToWithCourier(
                $this->item,
                3,
                GrossShipmentAllocation::class,
                (int) $allocation->id,
            );
            $this->fail('Expected ledger failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced ledger failure', $e->getMessage());
        }

        $afterFail = $this->balance()->fresh();
        $this->assertSame((int) $before->qty_on_hand_pieces, (int) $afterFail->qty_on_hand_pieces);
        $this->assertSame((int) $before->qty_with_courier_pieces, (int) $afterFail->qty_with_courier_pieces);
        $this->assertSame(
            0,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );

        $movement = $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            3,
            GrossShipmentAllocation::class,
            (int) $allocation->id,
            $this->manager,
        );

        $this->assertSame(GrossStockMovement::TYPE_SALE_OUT, $movement->type);
        $this->assertSame(5, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(3, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(8, $this->totalTracked());
    }

    public function test_insufficient_on_hand_no_partial_writes(): void
    {
        $this->stock->adjustIn($this->item, 2, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(5);

        try {
            $this->stock->repairMoveOnHandToWithCourier(
                $this->item,
                5,
                GrossShipmentAllocation::class,
                (int) $allocation->id,
            );
            $this->fail('Expected insufficient stock');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        $this->assertSame(2, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(0, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(2, $this->totalTracked());
        $this->assertSame(
            0,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );
    }

    public function test_missing_allocation_ref_fails_before_balance_mutation(): void
    {
        $this->stock->adjustIn($this->item, 5, GrossStockMovement::UNIT_PIECE, $this->manager);

        $this->expectException(ModelNotFoundException::class);
        $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            2,
            GrossShipmentAllocation::class,
            999999,
        );
    }

    public function test_lock_ordering_allocation_then_balance_within_one_transaction(): void
    {
        $this->stock->adjustIn($this->item, 6, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(2);

        DB::transaction(function () use ($allocation): void {
            $lockedAlloc = GrossShipmentAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertSame($allocation->id, $lockedAlloc->id);

            $lockedBalance = GrossStockBalance::query()
                ->where('item_id', $this->item->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertSame(6, (int) $lockedBalance->qty_on_hand_pieces);

            $this->stock->repairMoveOnHandToWithCourier(
                $this->item,
                2,
                GrossShipmentAllocation::class,
                (int) $allocation->id,
            );
        });

        $this->assertSame(4, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(2, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(6, $this->totalTracked());
        $this->assertDatabaseHas('gross_stock_movements', [
            'type' => GrossStockMovement::TYPE_SALE_OUT,
            'notes' => StockService::REPAIR_RETURNED_WITH_COURIER_NOTE,
            'ref_id' => $allocation->id,
            'qty_pieces' => 2,
        ]);
    }

    /**
     * Parallel first-write on MySQL. Skipped unless MYSQL_TEST_DATABASE is configured
     * to an isolated empty database (never shared business data).
     */
    #[Group('mysql-concurrency')]
    public function test_mysql_parallel_first_write_same_ref_exact_once(): void
    {
        $database = env('MYSQL_TEST_DATABASE');
        if (! is_string($database) || $database === '' || ! extension_loaded('pcntl')) {
            $this->markTestSkipped('Isolated MYSQL_TEST_DATABASE + pcntl required; not available safely here.');
        }

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL connection required for parallel fork verification.');
        }

        $this->stock->adjustIn($this->item, 20, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(5);
        $allocationId = (int) $allocation->id;
        $itemId = (int) $this->item->id;
        $pieces = 5;

        $pids = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                // Child: reconnect DB and attempt repair.
                DB::reconnect();
                try {
                    app(StockService::class)->repairMoveOnHandToWithCourier(
                        GrossItem::query()->findOrFail($itemId),
                        $pieces,
                        GrossShipmentAllocation::class,
                        $allocationId,
                    );
                    exit(0);
                } catch (\Throwable) {
                    exit(1);
                }
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $this->assertSame(
            1,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->where('ref_id', $allocationId)
                ->count(),
        );
        $b = GrossStockBalance::query()->where('item_id', $itemId)->firstOrFail();
        $this->assertSame(15, (int) $b->qty_on_hand_pieces);
        $this->assertSame(5, (int) $b->qty_with_courier_pieces);
    }
}
