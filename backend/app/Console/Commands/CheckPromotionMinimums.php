<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckPromotionMinimums extends Command
{
    protected $signature = 'promotion:check-minimums';

    protected $description = 'Flag active promotions that are below their minimum sales threshold';

    public function handle(): int
    {
        Log::info('promotion:check-minimums starting');

        $flaggedCount = 0;

        /**
         * Promotions with minimum_sales > 0 that haven't yet reached their threshold
         * and are not already flagged. `actual_sales` tracks cumulative sales on the row.
         */
        DB::table('promotions')
            ->where('status', 'active')
            ->where('minimum_sales', '>', 0)
            ->whereRaw('actual_sales < minimum_sales')
            ->where('below_minimum', false)
            ->chunkById(200, function ($promotions) use (&$flaggedCount) {
                $ids = $promotions->pluck('id')->all();

                DB::table('promotions')
                    ->whereIn('id', $ids)
                    ->update(['below_minimum' => true]);

                $flaggedCount += count($ids);
            });

        $this->info("Flagged {$flaggedCount} promotion(s) below minimum sales threshold.");
        Log::info('promotion:check-minimums done', ['flagged' => $flaggedCount]);

        return self::SUCCESS;
    }
}
