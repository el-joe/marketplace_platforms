<?php

namespace App\Console\Commands;

use App\Models\ProductPromoBadge;
use Illuminate\Console\Command;

class PurgePromoBadgePlaceholders extends Command
{
    protected $signature = 'promo-badges:purge-placeholders {--dry-run} {--pattern=hello world%}';
    protected $description = 'Delete placeholder promo badge rows whose English or Arabic label matches a LIKE pattern';

    public function handle(): int
    {
        $pattern = (string) $this->option('pattern');
        $q = ProductPromoBadge::query()->where(fn ($q) => $q->where('label_en', 'like', $pattern)->orWhere('label_ar', 'like', $pattern));
        $count = (clone $q)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} placeholder badge(s) match '{$pattern}'.");
            return self::SUCCESS;
        }

        $deleted = $q->delete();
        $this->info("Deleted {$deleted} placeholder badge(s) matching '{$pattern}'.");
        return self::SUCCESS;
    }
}
