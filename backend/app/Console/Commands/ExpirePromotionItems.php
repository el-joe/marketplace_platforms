<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePromotionItems extends Command
{
    protected $signature = 'promotion:expire-items';

    protected $description = 'Expire promotions and promotion items whose end_date has passed';

    public function handle(): int
    {
        Log::info('promotion:expire-items starting');

        $promotionCount = 0;
        $itemCount = 0;

        DB::table('promotions')
            ->where('end_date', '<', now())
            ->where('status', '!=', 'expired')
            ->chunkById(200, function ($promotions) use (&$promotionCount) {
                $ids = $promotions->pluck('id')->all();
                DB::table('promotions')->whereIn('id', $ids)->update(['status' => 'expired']);
                $promotionCount += count($ids);
            });

        DB::table('promotion_items')
            ->where('end_date', '<', now())
            ->where('status', '!=', 'expired')
            ->chunkById(200, function ($items) use (&$itemCount) {
                $ids = $items->pluck('id')->all();
                DB::table('promotion_items')->whereIn('id', $ids)->update(['status' => 'expired']);
                $itemCount += count($ids);
            });

        $this->info("Expired {$promotionCount} promotion(s) and {$itemCount} promotion item(s).");
        Log::info('promotion:expire-items done', ['promotions' => $promotionCount, 'items' => $itemCount]);

        return self::SUCCESS;
    }
}
