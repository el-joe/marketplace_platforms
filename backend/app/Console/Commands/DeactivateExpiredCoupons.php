<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use Illuminate\Console\Command;

class DeactivateExpiredCoupons extends Command
{
    protected $signature = 'coupons:deactivate-expired';

    protected $description = "Deactivate coupons whose valid_until has passed";

    public function handle(): int
    {
        $count = Coupon::where('valid_until', '<', now())
            ->where('is_active', 1)
            ->update(['is_active' => 0]);

        $this->info("Deactivated {$count} expired coupon(s).");

        return self::SUCCESS;
    }
}
