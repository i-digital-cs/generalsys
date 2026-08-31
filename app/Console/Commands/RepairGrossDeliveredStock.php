<?php

namespace App\Console\Commands;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossStockBalance;
use App\Models\User;
use App\Services\Gross\SalesInvoiceService;
use Illuminate\Console\Command;

/**
 * Fixes DELIVERED sales invoices that still have qty_with_courier_pieces on allocations
 * (invoices marked delivered before stock-on-deliver was implemented).
 */
class RepairGrossDeliveredStock extends Command
{
    protected $signature = 'hrpro:repair-gross-delivered-stock
                            {--dry-run : List affected rows without changing data}';

    protected $description = 'Clear with-courier stock for already-delivered Gross sales invoices';

    public function handle(SalesInvoiceService $invoices): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invoiceIds = GrossShipmentAllocation::query()
            ->where('qty_with_courier_pieces', '>', 0)
            ->whereHas('line.invoice', fn ($q) => $q->where('status', GrossSalesInvoice::STATUS_DELIVERED))
            ->with('line.invoice:id,invoice_number')
            ->get()
            ->pluck('line.invoice')
            ->filter()
            ->unique('id')
            ->values();

        if ($invoiceIds->isEmpty()) {
            $this->info('No delivered invoices with stuck with-courier stock.');

            return self::SUCCESS;
        }

        $this->info('Affected invoices: '.$invoiceIds->count());

        foreach ($invoiceIds as $invoice) {
            $this->line('  · '.$invoice->invoice_number.' (#'.$invoice->id.')');
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $actor = User::query()
            ->whereIn('role', ['MANAGER', 'ADMIN'])
            ->orderBy('id')
            ->first();

        $repaired = 0;
        foreach ($invoiceIds as $invoice) {
            $invoices->markDelivered($invoice, $actor);
            $repaired++;
        }

        $remaining = GrossStockBalance::query()->where('qty_with_courier_pieces', '>', 0)->count();
        $this->info("Repaired {$repaired} invoice(s). Balances still with courier: {$remaining}.");

        return self::SUCCESS;
    }
}
