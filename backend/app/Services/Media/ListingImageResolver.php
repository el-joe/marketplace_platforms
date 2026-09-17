<?php

namespace App\Services\Media;

use App\DTO\ImageDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The single source of truth for "what images does this listing/variant show".
 *
 * Rule (do not deviate): a listing's image = the images of its variant
 * (product_images.product_variant_id = variant.id), ordered by
 * is_primary DESC, position ASC. Only when the variant has NO images does it
 * fall back to product-level images (product_id = product.id AND
 * product_variant_id IS NULL). The two sets are never mixed, and a variant
 * never shows another variant's images.
 *
 * forVariants() is batch-oriented: no matter how many variant ids are
 * passed, it issues at most one query for variant-level images and one
 * query for product-level fallback images (plus, only on a cold cache, one
 * cheap lookup query to map variant ids to their product id — not an image
 * query). Results are memoized for the lifetime of the request and cached
 * per-variant across requests; ProductImageObserver invalidates the cache
 * key for affected variants whenever a ProductImage is written or removed.
 */
class ListingImageResolver
{
    private const CACHE_TTL = 3600;

    /** @var array<string, ImageDTO[]> request-scoped memo, keyed by variant id */
    private array $memo = [];

    public static function cacheKey(string $variantId): string
    {
        return "variant-images:{$variantId}";
    }

    /**
     * @param Collection<int, string>|array<int, string> $variantIds
     * @return array<string, ImageDTO[]> keyed by variant id
     */
    public function forVariants(Collection|array $variantIds): array
    {
        $ids = collect($variantIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $result = [];
        $missing = [];

        foreach ($ids as $id) {
            if (array_key_exists($id, $this->memo)) {
                $result[$id] = $this->memo[$id];
            } else {
                $missing[] = $id;
            }
        }

        if (empty($missing)) {
            return $result;
        }

        // Pull from per-variant cache first; only truly cold ids hit the DB.
        $stillMissing = [];
        foreach ($missing as $id) {
            $value = Cache::get(self::cacheKey($id));
            if ($value !== null) {
                $dtos = collect($value)->map(fn (array $row) => new ImageDTO(
                    $row['id'],
                    $row['url'],
                    $row['alt'],
                    $row['is_primary'],
                    $row['position'],
                ))->all();
                $this->memo[$id] = $dtos;
                $result[$id] = $dtos;
            } else {
                $stillMissing[] = $id;
            }
        }

        if (!empty($stillMissing)) {
            $resolved = $this->resolveFromDatabase($stillMissing);

            foreach ($resolved as $variantId => $dtos) {
                $this->memo[$variantId] = $dtos;
                $result[$variantId] = $dtos;

                Cache::put(
                    self::cacheKey($variantId),
                    array_map(fn (ImageDTO $d) => $d->toArray(), $dtos),
                    self::CACHE_TTL
                );
            }
        }

        return $result;
    }

    public function primary(string $variantId): ?string
    {
        $images = $this->forVariants([$variantId])[$variantId] ?? [];

        return $images[0]->url ?? null;
    }

    /**
     * @return ImageDTO[]
     */
    public function gallery(string $variantId): array
    {
        return $this->forVariants([$variantId])[$variantId] ?? [];
    }

    public function clearMemo(): void
    {
        $this->memo = [];
    }

    /**
     * @param string[] $variantIds
     * @return array<string, ImageDTO[]>
     */
    private function resolveFromDatabase(array $variantIds): array
    {
        // Not an "image" query: cheap id -> product_id map, needed to run the
        // fallback query only for variants that need it.
        $productIdByVariant = DB::table('product_variants')
            ->whereIn('id', $variantIds)
            ->pluck('product_id', 'id');

        // Query 1: every variant-level image for the requested variants.
        $variantImages = DB::table('product_images')
            ->whereIn('product_variant_id', $variantIds)
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->get()
            ->groupBy('product_variant_id');

        $variantsNeedingFallback = [];
        foreach ($variantIds as $variantId) {
            if (!$variantImages->has($variantId)) {
                $variantsNeedingFallback[] = $variantId;
            }
        }

        $productIdsNeedingFallback = collect($variantsNeedingFallback)
            ->map(fn ($id) => $productIdByVariant->get($id))
            ->filter()
            ->unique()
            ->values();

        // Query 2: product-level (variant-agnostic) fallback images, batched
        // for every product that needs one.
        $productImages = $productIdsNeedingFallback->isEmpty() ? collect() : DB::table('product_images')
            ->whereIn('product_id', $productIdsNeedingFallback->all())
            ->whereNull('product_variant_id')
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->get()
            ->groupBy('product_id');

        $result = [];

        foreach ($variantIds as $variantId) {
            if ($variantImages->has($variantId)) {
                $result[$variantId] = $this->mapRows($variantImages->get($variantId));
                continue;
            }

            $productId = $productIdByVariant->get($variantId);
            $rows = $productId ? $productImages->get($productId) : null;
            $result[$variantId] = $rows ? $this->mapRows($rows) : [];
        }

        return $result;
    }

    /**
     * @param \Illuminate\Support\Collection $rows
     * @return ImageDTO[]
     */
    private function mapRows(Collection $rows): array
    {
        return $rows->values()->map(function ($row) {
            return new ImageDTO(
                (string) $row->id,
                $this->absoluteUrl($row->disk ?? 'public', $row->path),
                ['ar' => $row->alt_text_ar, 'en' => $row->alt_text_en],
                (bool) $row->is_primary,
                (int) $row->position,
            );
        })->all();
    }

    private function absoluteUrl(string $disk, string $path): string
    {
        $url = Storage::disk($disk)->url($path);

        if (app()->environment('production') && str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }
}
