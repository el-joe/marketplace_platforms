<?php

namespace App\Services\Customer;

use App\Models\ProductPromoBadge;

/**
 * Batched promo-badge resolution: badges owned by the specific listing if it
 * has any active ones, else active product-level badges, else [].
 * Always exactly one query, whatever the size.
 */
class PromoBadgeResolver
{
    public const TYPE_VENDOR = 'vendor';
    public const TYPE_ADMIN = 'admin';
    public const TYPE_MARKETER = 'marketer';

    private const OWNER_COLUMNS = [
        self::TYPE_VENDOR => 'vendor_listing_id',
        self::TYPE_ADMIN => 'admin_listing_id',
        self::TYPE_MARKETER => 'marketer_listing_id',
    ];

    /**
     * @param  iterable<array{0:?string,1:?string,2:?string}>  $tuples  (listing_type, listing_id, product_id)
     * @return array<string, array<int, array<string, mixed>>> keyed by key($type, $id, $productId)
     */
    public function resolve(iterable $tuples): array
    {
        $tuples = collect($tuples)->filter(fn ($t) => ($t[1] ?? null) || ($t[2] ?? null))->values();
        if ($tuples->isEmpty()) {
            return [];
        }

        $productIds = $tuples->pluck(2)->filter()->unique()->values();
        $idsByType = [];
        foreach ($tuples as [$type, $id]) {
            if ($id && isset(self::OWNER_COLUMNS[$type])) {
                $idsByType[$type][$id] = $id;
            }
        }

        $rows = ProductPromoBadge::query()
            ->where('is_active', true)
            ->where(function ($q) use ($idsByType, $productIds) {
                if ($productIds->isNotEmpty()) {
                    $q->orWhere(function ($w) use ($productIds) {
                        $w->whereIn('product_id', $productIds)->productLevel();
                    });
                }
                foreach ($idsByType as $type => $ids) {
                    $q->orWhereIn(self::OWNER_COLUMNS[$type], array_values($ids));
                }
            })
            ->orderBy('sort_order')
            ->get();

        $byListing = [];
        $byProduct = [];
        foreach ($rows as $b) {
            $owned = false;
            foreach (self::OWNER_COLUMNS as $type => $col) {
                if ($b->{$col}) {
                    $byListing[$type . ':' . $b->{$col}][] = $this->shape($b);
                    $owned = true;
                }
            }
            if (!$owned) {
                $byProduct[$b->product_id][] = $this->shape($b);
            }
        }

        $out = [];
        foreach ($tuples as [$type, $id, $productId]) {
            $out[self::key($type, $id, $productId)] =
                ($id ? ($byListing[$type . ':' . $id] ?? null) : null)
                ?? ($productId ? ($byProduct[$productId] ?? null) : null)
                ?? [];
        }

        return $out;
    }

    /** @var array<string, array> memo scoped to one request (see ownerCheck) */
    private array $memo = [];
    private ?object $memoOwner = null;

    /** Resolver bound once per app scope; memo additionally resets whenever the current request object changes. */
    public static function instance(): self
    {
        if (!app()->bound(self::class)) {
            app()->scoped(self::class);
        }
        return app(self::class);
    }

    private function ownerCheck(): void
    {
        $req = app()->bound('request') ? app('request') : null;
        if ($this->memoOwner !== $req) {
            $this->memo = [];
            $this->memoOwner = $req;
        }
    }

    /** Batch-resolve (1 query) and memoise so per-card lookups in loops do not query. */
    public function prime(iterable $tuples): void
    {
        $this->ownerCheck();
        foreach ($this->resolve($tuples) as $k => $v) {
            $this->memo[$k] = $v;
        }
    }

    /** Memoised lookup; falls back to a single-item resolve on a miss. */
    public function lookup(?string $type, ?string $listingId, ?string $productId): array
    {
        $this->ownerCheck();
        $k = self::key($type, $listingId, $productId);
        return $this->memo[$k] ?? $this->forOne($type, $listingId, $productId);
    }

    public function flushMemo(): void
    {
        $this->memo = [];
    }

    /** Tuples for eloquent listings (Vendor/Admin/Marketer) that have productVariant loaded. */
    public static function tuplesForListings(iterable $listings): array
    {
        $out = [];
        foreach ($listings as $l) {
            $out[] = [self::typeOf($l), $l->id, $l->productVariant?->product_id ?? $l->product_id ?? null];
        }
        return $out;
    }

    public static function typeOf($listing): string
    {
        return $listing instanceof \App\Models\AdminListing ? self::TYPE_ADMIN
            : ($listing instanceof \App\Models\MarketerListing ? self::TYPE_MARKETER : self::TYPE_VENDOR);
    }

    public function forOne(?string $type, ?string $listingId, ?string $productId): array
    {
        return $this->resolve([[$type, $listingId, $productId]])[self::key($type, $listingId, $productId)] ?? [];
    }

    public static function key(?string $type, ?string $listingId, ?string $productId): string
    {
        return ($type ?? '') . ':' . ($listingId ?? '') . ':' . ($productId ?? '');
    }

    private function shape($b): array
    {
        return [
            'id' => $b->id,
            'label' => ['ar' => $b->label_ar, 'en' => $b->label_en],
            'icon_key' => $b->icon_key,
            'color_hex' => $b->color_hex,
            'text_color_hex' => $b->text_color_hex,
            'sort_order' => $b->sort_order,
        ];
    }
}
