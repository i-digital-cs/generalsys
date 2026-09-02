<?php

namespace App\Console\Commands;

use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossStockBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fixes allocations where partial returns prematurely moved stock to on-hand.
 */
class RepairGrossReturnedWithCourierCommand extends Command
{
    protected $signature = 'gross:repair-returned-with-courier {--force : Apply without confirmation}';

    protected $description = 'Restore client-returned pieces to with-courier until COD handover';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Repair returned pieces still with courier?', true)) {
            return self::SUCCESS;
        }

        $fixed = 0;

        DB::transaction(function () use (&$fixed): void {
            $legacyRefused = GrossShipmentAllocation::query()
                ->where('qty_returned_pieces', '>', 0)
                ->whereColumn('qty_returned_received_pieces', 'qty_returned_pieces')
                ->where('qty_with_courier_pieces', 0)
                ->whereHas('line.invoice', fn ($q) => $q->where('status', 'REFUSED'))
                ->with('line.item')
                ->get();

            foreach ($legacyRefused as $allocation) {
                $pending = (int) $allocation->qty_returned_pieces;
                $item = $allocation->line?->item;
                if ($item === null || $pending < 1) {
                    continue;
                }

                /** @var GrossStockBalance|null $balance */
                $balance = GrossStockBalance::query()
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if ($balance !== null) {
                    if ($balance->qty_on_hand_pieces >= $pending) {
                        $balance->qty_on_hand_pieces -= $pending;
                    }
                    $balance->qty_with_courier_pieces += $pending;
                    $balance->save();
                }

                $allocation->qty_returned_received_pieces = 0;
                $allocation->qty_with_courier_pieces = $pending;
                $allocation->save();
                $allocation->assertInvariant();
                $fixed++;
            }

            $allocations = GrossShipmentAllocation::query()
                ->where('qty_returned_pieces', '>', 0)
                ->with('line.item')
                ->get();

            foreach ($allocations as $allocation) {
                $pending = max(0, (int) $allocation->qty_returned_pieces - (int) $allocation->qty_returned_received_pieces);
                if ($pending < 1) {
                    continue;
                }

                if ((int) $allocation->qty_with_courier_pieces >= $pending) {
                    continue;
                }

                $item = $allocation->line?->item;
                if ($item === null) {
                    continue;
                }

                /** @var GrossStockBalance $balance */
                $balance = GrossStockBalance::query()
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null) {
                    continue;
                }

                if ($balance->qty_on_hand_pieces >= $pending) {
                    $balance->qty_on_hand_pieces -= $pending;
                }
                $balance->qty_with_courier_pieces += $pending;
                $balance->save();

                $allocation->qty_with_courier_pieces += $pending;
                if ($allocation->qty_returned_received_pieces > 0) {
                    $allocation->qty_returned_received_pieces = max(
                        0,
                        (int) $allocation->qty_returned_received_pieces - $pending,
                    );
                }
                $allocation->save();
                $allocation->assertInvariant();

                $fixed++;
            }
        });

        $this->info("Repaired {$fixed} allocation(s).");

        return self::SUCCESS;
    }
}
