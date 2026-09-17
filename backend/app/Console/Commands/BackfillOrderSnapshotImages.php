<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use App\Services\Media\ListingImageResolver;
use Illuminate\Console\Command;

/**
 * enhancement.md P-17 task 5: existing order_items were written before
 * product_snapshot carried a resolved absolute image URL + variant_id.
 * Backfills every row whose snapshot is missing those keys, using the
 * item's own product_variant_id (never another variant's images) and the
 * variant-first/product-fallback rule via ListingImageResolver.
 */
class BackfillOrderSnapshotImages extends Command
{
    protected $signature = 'orders:backfill-snapshot-images {--chunk=500}';

    protected $description = 'Backfill order_items.product_snapshot with resolved absolute image URLs and variant_id';

    public function handle(ListingImageResolver $resolver): int
    {
        $chunkSize = (int) $this->option('chunk');
        $updated = 0;
        $skipped = 0;

        OrderItem::query()
            ->whereNotNull('product_variant_id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($items) use ($resolver, &$updated, &$skipped) {
                $variantIds = $items->pluck('product_variant_id')->filter()->unique()->values();
                $imagesByVariant = $resolver->forVariants($variantIds);

                foreach ($items as $item) {
                    $snapshot = $item->product_snapshot ?? [];

                    if (($snapshot['image']['url'] ?? null) && ($snapshot['variant_id'] ?? null)) {
                        $skipped++;
                        continue;
                    }

                    $images = $imagesByVariant[$item->product_variant_id] ?? [];
                    $primaryUrl = $images[0]->url ?? null;

                    $snapshot['variant_id'] = $item->product_variant_id;
                    $snapshot['thumbnail_url'] = $primaryUrl;
                    $snapshot['primary_image_url'] = $primaryUrl;
                    $snapshot['image'] = $primaryUrl ? ['url' => $primaryUrl, 'alt' => $images[0]->alt] : null;
                    $snapshot['images'] = array_map(fn ($i) => $i->toArray(), $images);

                    $item->product_snapshot = $snapshot;
                    $item->save();
                    $updated++;
                }
            });

        $this->info("Backfilled {$updated} order item snapshot(s), skipped {$skipped} already up to date.");

        return self::SUCCESS;
    }
}
