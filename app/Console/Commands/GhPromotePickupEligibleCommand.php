<?php

namespace App\Console\Commands;

use App\Services\GeneralHouse\Orders\GhOrderStateService;
use Illuminate\Console\Command;

class GhPromotePickupEligibleCommand extends Command
{
    protected $signature = 'gh:promote-pickup-eligible';

    protected $description = 'Promote GH LATER orders to PICKUP when within the 24h eligibility window';

    public function handle(GhOrderStateService $states): int
    {
        $count = $states->promoteEligibleLaterOrders();
        $this->info("Promoted {$count} order(s) to PICKUP.");

        return self::SUCCESS;
    }
}
