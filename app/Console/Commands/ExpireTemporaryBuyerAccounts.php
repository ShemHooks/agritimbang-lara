<?php

namespace App\Console\Commands;

use App\Models\Buyer;
use Illuminate\Console\Command;

class ExpireTemporaryBuyerAccounts extends Command
{
    protected $signature = 'buyers:expire-temporary-accounts';

    protected $description = 'Expire temporary buyer accounts whose auction date has passed.';

    public function handle(): int
    {
        $count = Buyer::query()
            ->where('status', 'temporary')
            ->where('expires_at', '<=', now('Asia/Manila'))
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} temporary accounts.");

        return self::SUCCESS;
    }
}
