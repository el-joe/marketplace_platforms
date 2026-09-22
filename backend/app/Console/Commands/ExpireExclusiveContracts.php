<?php

namespace App\Console\Commands;

use App\Models\ExclusiveContract;
use Illuminate\Console\Command;

class ExpireExclusiveContracts extends Command
{
    protected $signature = 'exclusive-contracts:expire';

    protected $description = 'Flip active/pending exclusive contracts whose ends_at has passed to expired status';

    public function handle(): int
    {
        $activated = ExclusiveContract::where('status', 'pending')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->update(['status' => 'active']);

        $expired = ExclusiveContract::whereIn('status', ['active', 'pending'])
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Activated {$activated} and expired {$expired} exclusive contract(s).");

        return self::SUCCESS;
    }
}
