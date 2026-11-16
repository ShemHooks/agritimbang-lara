<?php

namespace App\Console\Commands;

use App\Services\AuctionScheduleService;
use Illuminate\Console\Command;

class SyncAuctionScheduleStatuses extends Command
{
    protected $signature = 'auctions:sync-statuses';

    protected $description = 'Automatically move published auctions to ongoing and completed based on their schedule times.';

    public function handle(AuctionScheduleService $service): int
    {
        $updated = $service->syncAutomaticStatuses();
        $this->info("Synchronized auction schedule statuses. {$updated} schedule(s) updated.");

        return self::SUCCESS;
    }
}
