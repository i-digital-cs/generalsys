<?php

namespace App\Console\Commands;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossStockBalance;
use App\Services\Gross\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restores client-returned pieces to with-courier when allocation state is inconsistent.
 *
 * Default: dry-run report (no writes). Persist only with --apply.
 * RISK-GROSS-001: never credits WITH_COURIER without an ON_HAND debit.
 */
class RepairGrossReturnedWithCourierCommand extends Command
{
    protected $signature = 'gross:repair-returned-with-courier
                            {--apply : Persist stock and allocation repairs (default is dry-run)}
                            {--force : Skip confirmation prompt when using --apply}';

    protected $description = 'Repair returned pieces still with courier (dry-run by default; use --apply to persist)';

    public function __construct(private readonly StockService $stock)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply) {
            if (! $this->option('force') && ! $this->confirm('Apply stock/allocation repairs now?', false)) {
                $this->warn('Aborted. No changes written.');

                return self::FAILURE;
            }
            $this->warn('APPLY mode — balances and allocations will be updated.');
        } else {
            $this->info('Dry-run (default). Pass --apply to persist changes.');
        }

        $eligible = 0;
        $repaired = 0;
        $skipped = 0;
        $failed = 0;

        $candidates = $this->collectCandidates();
        $eligible = count($candidates);

        foreach ($candidates as $candidate) {
            /** @var GrossShipmentAllocation $allocation */
            $allocation = $candidate['allocation'];
            $pending = (int) $candidate['pending'];
            $mode = (string) $candidate['mode'];

            $item = $allocation->line?->item;
            if ($item === null || $pending < 1) {
                $skipped++;
                $this->line(sprintf(
                    'SKIP allocation_id=%d reason=missing_item_or_qty mode=%s',
                    $allocation->id,
                    $mode,
                ));

                continue;
            }

            $onHand = (int) (GrossStockBalance::query()
                ->where('item_id', $item->id)
                ->value('qty_on_hand_pieces') ?? 0);

            if ($onHand < $pending) {
                $skipped++;
                $this->warn(sprintf(
                    'SKIP allocation_id=%d item_id=%d pending=%d on_hand=%d reason=insufficient_on_hand mode=%s',
                    $allocation->id,
                    $item->id,
                    $pending,
                    $onHand,
                    $mode,
                ));

                continue;
            }

            if (! $apply) {
                $repaired++;
                $this->line(sprintf(
                    'DRY-RUN allocation_id=%d item_id=%d pending=%d on_hand=%d mode=%s would transfer + write SALE_OUT',
                    $allocation->id,
                    $item->id,
                    $pending,
                    $onHand,
                    $mode,
                ));

                continue;
            }

            try {
                $didRepair = false;

                DB::transaction(function () use ($allocation, $item, $pending, $mode, &$didRepair): void {
                    // Lock order matches StockService: allocation → (balance inside repair) → writes.
                    /** @var GrossShipmentAllocation $locked */
                    $locked = GrossShipmentAllocation::query()
                        ->whereKey($allocation->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $this->stillEligible($locked, $mode, $pending)) {
                        return;
                    }

                    $this->stock->repairMoveOnHandToWithCourier(
                        $item,
                        $pending,
                        GrossShipmentAllocation::class,
                        (int) $locked->id,
                    );

                    if ($mode === 'legacy_refused') {
                        $locked->qty_returned_received_pieces = 0;
                        $locked->qty_with_courier_pieces = $pending;
                    } else {
                        $locked->qty_with_courier_pieces += $pending;
                        if ($locked->qty_returned_received_pieces > 0) {
                            $locked->qty_returned_received_pieces = max(
                                0,
                                (int) $locked->qty_returned_received_pieces - $pending,
                            );
                        }
                    }

                    $locked->save();
                    $locked->assertInvariant();
                    $didRepair = true;
                });

                if (! $didRepair) {
                    $skipped++;
                    $this->line(sprintf(
                        'SKIP allocation_id=%d reason=no_longer_eligible mode=%s',
                        $allocation->id,
                        $mode,
                    ));

                    continue;
                }

                $repaired++;
                $this->info(sprintf(
                    'APPLIED allocation_id=%d item_id=%d pending=%d mode=%s',
                    $allocation->id,
                    $item->id,
                    $pending,
                    $mode,
                ));
            } catch (GrossException $e) {
                $skipped++;
                $this->warn(sprintf(
                    'SKIP allocation_id=%d item_id=%d pending=%d reason=%s mode=%s',
                    $allocation->id,
                    $item->id,
                    $pending,
                    $e->getMessage(),
                    $mode,
                ));
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf(
                    'FAIL allocation_id=%d reason=unexpected',
                    $allocation->id,
                ));
                report($e);
            }
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            [
                ['eligible', $eligible],
                ['repaired_or_would_repair', $repaired],
                ['skipped', $skipped],
                ['failed', $failed],
                ['mode', $apply ? 'apply' : 'dry-run'],
            ],
        );

        if ($failed > 0 || $skipped > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Re-evaluate eligibility after lockForUpdate (concurrent apply may have already repaired).
     */
    private function stillEligible(GrossShipmentAllocation $locked, string $mode, int $pending): bool
    {
        if ($pending < 1) {
            return false;
        }

        if ($mode === 'legacy_refused') {
            return (int) $locked->qty_returned_pieces === $pending
                && (int) $locked->qty_returned_received_pieces === (int) $locked->qty_returned_pieces
                && (int) $locked->qty_with_courier_pieces === 0;
        }

        $currentPending = max(
            0,
            (int) $locked->qty_returned_pieces - (int) $locked->qty_returned_received_pieces,
        );

        return $currentPending === $pending
            && (int) $locked->qty_with_courier_pieces < $pending;
    }

    /**
     * @return list<array{allocation: GrossShipmentAllocation, pending: int, mode: string}>
     */
    private function collectCandidates(): array
    {
        $out = [];
        $seen = [];

        $legacyRefused = GrossShipmentAllocation::query()
            ->where('qty_returned_pieces', '>', 0)
            ->whereColumn('qty_returned_received_pieces', 'qty_returned_pieces')
            ->where('qty_with_courier_pieces', 0)
            ->whereHas('line.invoice', fn ($q) => $q->where('status', 'REFUSED'))
            ->with('line.item')
            ->get();

        foreach ($legacyRefused as $allocation) {
            $pending = (int) $allocation->qty_returned_pieces;
            if ($pending < 1) {
                continue;
            }
            $seen[$allocation->id] = true;
            $out[] = [
                'allocation' => $allocation,
                'pending' => $pending,
                'mode' => 'legacy_refused',
            ];
        }

        $allocations = GrossShipmentAllocation::query()
            ->where('qty_returned_pieces', '>', 0)
            ->with('line.item')
            ->get();

        foreach ($allocations as $allocation) {
            if (isset($seen[$allocation->id])) {
                continue;
            }

            $pending = max(0, (int) $allocation->qty_returned_pieces - (int) $allocation->qty_returned_received_pieces);
            if ($pending < 1) {
                continue;
            }

            if ((int) $allocation->qty_with_courier_pieces >= $pending) {
                continue;
            }

            $out[] = [
                'allocation' => $allocation,
                'pending' => $pending,
                'mode' => 'under_courier',
            ];
        }

        return $out;
    }
}
