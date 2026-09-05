<?php

namespace App\Console\Commands;

use App\Models\PageBlock;
use Illuminate\Console\Command;

class BackfillPageBuilderTranslations extends Command
{
    protected $signature = 'page-builder:backfill-translations';

    protected $description = 'Normalize legacy single-image config keys into _en/_ar pairs for promo_tiles and video_banner blocks.';

    public function handle(): int
    {
        $this->backfillPromoTiles();
        $this->backfillVideoBanner();

        return self::SUCCESS;
    }

    private function backfillPromoTiles(): void
    {
        $count = 0;

        PageBlock::where('block_type', 'promo_tiles')->whereNotNull('config')->chunkById(100, function ($blocks) use (&$count) {
            foreach ($blocks as $block) {
                $config = $block->config ?? [];
                $tiles = $config['tiles'] ?? [];
                $changed = false;

                foreach ($tiles as $i => $tile) {
                    if (!array_key_exists('image_url_en', $tile) && array_key_exists('image_url', $tile)) {
                        $tiles[$i]['image_url_en'] = $tile['image_url'];
                        $changed = true;
                    }
                    if (!array_key_exists('image_url_ar', $tiles[$i])) {
                        $tiles[$i]['image_url_ar'] = $tile['image_url_ar'] ?? ($tile['image_url'] ?? null);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $config['tiles'] = $tiles;
                    $block->update(['config' => $config]);
                    $count++;
                }
            }
        });

        $this->info("promo_tiles blocks updated: {$count}");
    }

    private function backfillVideoBanner(): void
    {
        $count = 0;

        PageBlock::where('block_type', 'video_banner')->whereNotNull('config')->chunkById(100, function ($blocks) use (&$count) {
            foreach ($blocks as $block) {
                $config = $block->config ?? [];
                $changed = false;

                if (!array_key_exists('poster_url_en', $config) && array_key_exists('poster_url', $config)) {
                    $config['poster_url_en'] = $config['poster_url'];
                    $changed = true;
                }
                if (!array_key_exists('poster_url_ar', $config)) {
                    $config['poster_url_ar'] = $config['poster_url_ar'] ?? ($config['poster_url'] ?? null);
                    $changed = true;
                }

                if ($changed) {
                    $block->update(['config' => $config]);
                    $count++;
                }
            }
        });

        $this->info("video_banner blocks updated: {$count}");
    }
}
