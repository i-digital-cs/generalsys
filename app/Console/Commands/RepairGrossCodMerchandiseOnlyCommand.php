<?php

namespace App\Console\Commands;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossTreasuryVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Legacy COD collections included shipping fee in treasury — courier keeps shipping; merchant receives merchandise only.
 */
class RepairGrossCodMerchandiseOnlyCommand extends Command
{
    protected $signature = 'gross:repair-cod-merchandise-only {--force : Apply without confirmation}';

    protected $description = 'Set COD collected + COURIER_COD treasury receipts to merchandise only (exclude shipping fee)';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Fix COD/treasury amounts to merchandise only (exclude shipping)?', true)) {
            return self::SUCCESS;
        }

        $fixed = 0;

        DB::transaction(function () use (&$fixed): void {
            $invoices = GrossSalesInvoice::query()
                ->where('cod_collection_status', GrossSalesInvoice::COD_COLLECTED)
                ->where('shipping_fee', '>', 0)
                ->get();

            foreach ($invoices as $invoice) {
                $merchandise = round($invoice->courierMerchandiseAmount(), 2);
                $collected = round((float) ($invoice->cod_collected_amount ?? 0), 2);

                if ($merchandise <= 0 || abs($collected - $merchandise) <= 0.009) {
                    continue;
                }

                $invoice->update(['cod_collected_amount' => $merchandise]);

                $voucher = GrossTreasuryVoucher::query()
                    ->where('purpose', GrossTreasuryVoucher::PURPOSE_COURIER_COD)
                    ->where('sales_invoice_id', $invoice->id)
                    ->first();

                if ($voucher !== null) {
                    $voucher->update(['amount' => $merchandise]);
                }

                $this->line(sprintf(
                    'Invoice %s: COD %s → %s%s',
                    $invoice->invoice_number,
                    number_format($collected, 2, '.', ''),
                    number_format($merchandise, 2, '.', ''),
                    $voucher ? ' (treasury '.$voucher->voucher_number.')' : '',
                ));

                $fixed++;
            }
        });

        $this->info("Repaired {$fixed} invoice(s).");

        return self::SUCCESS;
    }
}
