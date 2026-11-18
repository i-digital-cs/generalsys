<?php

namespace App\Console\Commands;

use App\Services\GeneralHouse\Orders\GhOrderStateService;
use Illuminate\Console\Command;

class GhPromotePickupEligibleCommand extends Command
{
    protected $signature = 'gh:promote-pickup-eligible';

    protected $description = 'Promote GH LATER→PICKUP (on pickup date, Asia/Dubai) and WORKING→DELIVERY (timing rules; invoice not required)';

    public function handle(GhOrderStateService $states): int
    {
        $pickup = $states->promoteEligibleLaterOrders();
        $delivery = $states->promoteEligibleWorkingToDelivery();
        $this->info("Promoted {$pickup} order(s) to PICKUP; {$delivery} order(s) to DELIVERY.");

        return self::SUCCESS;
    }
}
