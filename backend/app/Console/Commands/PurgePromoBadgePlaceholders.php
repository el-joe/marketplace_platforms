<?php

namespace App\Console\Commands;

use App\Models\ProductPromoBadge;
use Illuminate\Console\Command;

class PurgePromoBadgePlaceholders extends Command
{
    protected $signature = 'promo-badges:purge-placeholders {--dry-run} {--force} {--pattern=hello world%}';
    protected $description = 'Delete placeholder promo badge rows whose English or Arabic label matches a LIKE pattern';

    public function handle(): int
    {
        $pattern = (string) $this->option('pattern');

        // Refuse near-wildcard patterns: this deletes rows of every owner type.
        if (mb_strlen(str_replace(['%', '_'], '', $pattern)) < 5) {
            $this->error('Pattern must contain at least 5 literal characters.');
            return self::FAILURE;
        }

        $q = ProductPromoBadge::query()->where(fn ($q) => $q->where('label_en', 'like', $pattern)->orWhere('label_ar', 'like', $pattern));
        $count = (clone $q)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} placeholder badge(s) match '{$pattern}'.");
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} badge(s) matching '{$pattern}'?")) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        $deleted = $q->delete();
        $this->info("Deleted {$deleted} placeholder badge(s) matching '{$pattern}'.");
        return self::SUCCESS;
    }
}
