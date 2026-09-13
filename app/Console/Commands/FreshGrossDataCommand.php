<?php

namespace App\Console\Commands;

use App\Services\Gross\GrossDataResetService;
use Illuminate\Console\Command;

class FreshGrossDataCommand extends Command
{
    protected $signature = 'gross:fresh
                            {--force : Skip confirmation (required on production)}';

    protected $description = 'Fresh Mohra Gross ONLY: items, categories, color/size/model, stock, sales, purchases, treasury. Does NOT touch valuation or HR.';

    /** @var list<string> */
    protected $aliases = ['mohra:fresh'];

    public function handle(GrossDataResetService $reset): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('On production, run: php artisan mohra:fresh --force');
            $this->line('  (same as: php artisan gross:fresh --force)');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Delete ALL Mohra Gross data only (items, categories, color/size/model, stock, invoices, customers, suppliers, treasury)? Valuation sheets and HR stay untouched.',
            false,
        )) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $reset->fresh();

        $this->info('Mohra Gross reset complete.');
        $this->line('Cleared: items · categories · color/size/model · stock · sales · purchases · customers · suppliers · shipping · treasury vouchers · product expense budgets.');
        $this->line('Kept: valuation sheets/assignments · HR · users.');
        $this->line('Re-seeded: Cash + Bank (EGP), expense types (Petrol, Rent, Electricity, Other).');

        return self::SUCCESS;
    }
}
