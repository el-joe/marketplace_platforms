<?php

namespace App\Services\Shared;

use App\Models\ProductPromoBadge;
use App\Models\AdminListing;
use App\Models\MarketerListing;
use App\Models\Product;
use App\Models\VendorListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replace-style sync of rotating promo badges for one owner: the product itself
 * (product-level, admin-managed) or a single vendor / admin / marketer listing.
 */
class PromoBadgeSyncService
{
    public const OWNER_COLUMNS = ['vendor_listing_id', 'admin_listing_id', 'marketer_listing_id'];

    /** Validation rules for a standalone `promo_badges` array (listing editors). */
    public static function rules(): array
    {
        return [
            'promo_badges' => ['nullable', 'array', 'max:' . config('promo_badges.max_per_owner', 10)],
            'promo_badges.*.id' => ['nullable', 'uuid'],
            'promo_badges.*.label_en' => ['required', 'string', 'max:100'],
            'promo_badges.*.label_ar' => ['required', 'string', 'max:100'],
            'promo_badges.*.icon_key' => ['nullable', 'string', 'max:50', function ($attribute, $value, $fail) {
                // Case/format-insensitive so legacy keys ("truck", "shield-check") still validate.
                if (filled($value) && ! in_array(Str::studly($value), config('promo_badges.icons', []), true)) {
                    $fail('The selected icon is not allowed.');
                }
            }],
            'promo_badges.*.color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'promo_badges.*.text_color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'promo_badges.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  string       $productId
     * @param  string|null  $ownerColumn  one of OWNER_COLUMNS, or null for product-level
     * @param  string|null  $ownerId      the listing id when $ownerColumn is set
     * @param  array        $badges       submitted rows; rows missing either label are dropped
     */
    public function sync(string $productId, ?string $ownerColumn, ?string $ownerId, array $badges): void
    {
        $scope = fn () => ProductPromoBadge::query()
            ->where('product_id', $productId)
            ->where(function ($q) use ($ownerColumn, $ownerId) {
                foreach (self::OWNER_COLUMNS as $col) {
                    $col === $ownerColumn ? $q->where($col, $ownerId) : $q->whereNull($col);
                }
            });

        $rows = collect($badges)
            ->filter(fn ($b) => filled($b['label_en'] ?? null) && filled($b['label_ar'] ?? null))
            ->values();
        $incomingIds = $rows->pluck('id')->filter()->all();

        DB::transaction(function () use ($scope, $rows, $incomingIds, $productId, $ownerColumn, $ownerId) {
            $scope()->when(!empty($incomingIds), fn ($q) => $q->whereNotIn('id', $incomingIds))->delete();

            foreach ($rows as $i => $b) {
                $payload = [
                    'label_en' => $b['label_en'],
                    'label_ar' => $b['label_ar'],
                    'icon_key' => filled($b['icon_key'] ?? null) ? Str::studly($b['icon_key']) : 'Tag',
                    'color_hex' => $b['color_hex'] ?? '#1a1a2e',
                    'text_color_hex' => $b['text_color_hex'] ?? '#FFFFFF',
                    'sort_order' => $i,
                    'is_active' => filter_var($b['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];

                $existing = filled($b['id'] ?? null) ? $scope()->where('id', $b['id'])->first() : null;

                $existing
                    ? $existing->update($payload)
                    : ProductPromoBadge::create($payload + ['product_id' => $productId] + ($ownerColumn ? [$ownerColumn => $ownerId] : []));
            }
        });

        // If a caller wraps sync() in its own transaction, defer until it commits so a
        // concurrent request cannot re-cache the pre-commit rows.
        DB::afterCommit(fn () => $this->bustCaches($productId, $ownerColumn, $ownerId));
    }

    /**
     * Cache invalidation after a sync. Only reuses existing bust methods:
     *  - vendor/admin listing owner: PageCacheService::bustVendorListing/bustAdminListing
     *    (buy-box cache, PDP delivery options, page blocks pinned to that listing).
     *  - marketer listing owner: no dedicated bust exists and marketer listings are not
     *    cached by CachedListingResolver, so only the PDP delivery-options cache for that
     *    product+country is busted.
     *  - product-level: the badge is embedded in every listing view of the product, so
     *    bust each vendor/admin listing of its variants and the delivery options for
     *    each marketer listing's country.
     * Bust failures never fail the save.
     */
    private function bustCaches(string $productId, ?string $ownerColumn, ?string $ownerId): void
    {
        try {
            $cache = app(PageCacheService::class);

            if ($ownerColumn === 'vendor_listing_id') {
                $l = VendorListing::with('productVariant')->find($ownerId);
                $l && $cache->bustVendorListing($l);
            } elseif ($ownerColumn === 'admin_listing_id') {
                $l = AdminListing::with('productVariant')->find($ownerId);
                $l && $cache->bustAdminListing($l);
            } elseif ($ownerColumn === 'marketer_listing_id') {
                $l = MarketerListing::find($ownerId);
                $l && $cache->bustProductDeliveryOptions($productId, $l->country_id);
            } else {
                $product = Product::find($productId);
                if (! $product) {
                    return;
                }
                $variantIds = $product->variants()->pluck('id');
                VendorListing::with('productVariant')->whereIn('product_variant_id', $variantIds)
                    ->get()->each(fn ($l) => $cache->bustVendorListing($l));
                AdminListing::with('productVariant')->whereIn('product_variant_id', $variantIds)
                    ->get()->each(fn ($l) => $cache->bustAdminListing($l));
                MarketerListing::whereIn('product_variant_id', $variantIds)->pluck('country_id')->unique()
                    ->each(fn ($c) => $cache->bustProductDeliveryOptions($productId, $c));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
