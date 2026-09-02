<?php

namespace App\Console\Commands;

use App\Services\Gross\GrossDataResetService;
use Illuminate\Console\Command;

class FreshGrossDataCommand extends Command
{
    protected $signature = 'gross:fresh
                            {--force : Skip confirmation (required on production)}';

    protected $description = 'Delete all Mohra Gross data and re-seed treasury accounts + expense types';

    public function handle(GrossDataResetService $reset): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('On production, run: php artisan gross:fresh --force');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This deletes ALL Mohra Gross data (items, stock, sales, purchases, treasury vouchers). HR data is kept. Continue?',
            false,
        )) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $reset->fresh();

        $this->info('Mohra Gross data reset complete.');
        $this->line('Treasury: Cash + Bank (EGP) and default expense types re-seeded.');
        $this->line('You can now run Phase 12 manual tests from a clean slate.');

        return self::SUCCESS;
    }
}
