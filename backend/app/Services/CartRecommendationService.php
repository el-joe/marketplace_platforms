<?php

namespace App\Services;

use App\Models\AdminListing;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Country;
use App\Models\Customer;
use App\Models\FrequentlyBoughtTogetherItem;
use App\Models\ProductView;
use App\Models\VendorListing;
use App\Services\Customer\ListingQueryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CartRecommendationService
{
    private const SECTION_LIMIT = 12;
    private const CACHE_TTL     = 300;
    private const SECTIONS_ORDER = [
        'frequently_bought_together',
        'from_your_categories',
        'best_sellers',
        'recently_viewed',
    ];

    // Eager-load columns needed by toCardShape() / toAdminCardShape()
    private const VENDOR_WITH = [
        'productVariant.images',
        'productVariant.product.images',
        'productVariant.product.category:id,name_en,name_ar,slug',
        'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
        'vendor:id,store_name,store_rating_avg',
        'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
    ];

    private const ADMIN_WITH = [
        'productVariant.images',
        'productVariant.product.images',
        'productVariant.product.category:id,name_en,name_ar,slug',
        'productVariant.product.brand:id,name_en,name_ar,slug,logo_media_id',
        'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
    ];

    public function __construct(
        private readonly ListingQueryService $listingQuery,
    ) {}

    public function getRecommendations(Cart $cart, ?Customer $customer, bool $isNawyNow): array
    {
        $cartItems       = CartItem::where('cart_id', $cart->id)->get();
        $cartListingIds  = $this->extractListingIds($cartItems, $isNawyNow);
        $cartProductIds  = $this->extractProductIds($cartItems, $isNawyNow);
        $cartCategoryIds = $this->extractCategoryIds($cartItems, $isNawyNow);
        $countryId       = $cart->country_id;
        $currency        = $cart->currency;
        $country         = Country::find($countryId);

        // wishlist IDs for the authenticated customer (empty for guests)
        $wishlistIds = $this->listingQuery->wishlistListingIds($customer?->id);

        $sections = [];

        foreach (self::SECTIONS_ORDER as $type) {
            $items = match ($type) {
                'frequently_bought_together' => $this->fbtSection(
                    $cartProductIds, $cartListingIds, $countryId, $currency,
                    $isNawyNow, $country, $wishlistIds,
                ),
                'from_your_categories' => $this->fromCategoriesSection(
                    $cartCategoryIds, $cartListingIds, $countryId, $currency,
                    $isNawyNow, $country, $wishlistIds,
                ),
                'best_sellers' => $this->bestSellersSection(
                    $cartListingIds, $countryId, $currency,
                    $isNawyNow, $country, $wishlistIds,
                ),
                'recently_viewed' => $customer
                    ? $this->recentlyViewedSection(
                        $customer, $cartProductIds, $cartListingIds, $countryId, $currency,
                        $isNawyNow, $country, $wishlistIds,
                    )
                    : collect(),
            };

            if ($items->isNotEmpty()) {
                $sections[] = [
                    'section_type' => $type,
                    'title'        => [
                        'en' => $this->sectionTitle($type, 'en'),
                        'ar' => $this->sectionTitle($type, 'ar'),
                    ],
                    'listings'     => $items->values()->toArray(),
                ];
            }
        }

        return $sections;
    }

    // ─── Cache helper ───────────────────────────────────────────────────────

    /**
     * Like Cache::remember(), but discards and recomputes on a corrupt/stale
     * cached value (e.g. __PHP_Incomplete_Class from a renamed model after deploy).
     */
    private function cacheRememberCollection(string $key, \Closure $callback): Collection
    {
        $value = Cache::get($key);

        if ($value instanceof Collection) {
            return $value;
        }

        if ($value !== null) {
            Cache::forget($key);
        }

        $value = $callback();
        Cache::put($key, $value, self::CACHE_TTL);

        return $value;
    }

    // ─── Section builders ─────────────────────────────────────────────────────

    private function fbtSection(
        array $cartProductIds, array $cartListingIds,
        string $countryId, string $currency,
        bool $isNawyNow, ?Country $country, array $wishlistIds,
    ): Collection {
        if (empty($cartProductIds)) {
            return collect();
        }

        $cacheKey = 'cart_recs:fbt:' . md5(implode(',', $cartProductIds)) . ":{$countryId}:{$isNawyNow}";

        return $this->cacheRememberCollection($cacheKey, function () use (
            $cartProductIds, $cartListingIds, $countryId, $currency,
            $isNawyNow, $country, $wishlistIds,
        ) {
            $relatedProductIds = FrequentlyBoughtTogetherItem::whereIn('product_id', $cartProductIds)
                ->orderBy('position')
                ->pluck('related_product_id')
                ->unique()
                ->values();

            if ($relatedProductIds->isEmpty()) {
                return collect();
            }

            return $this->resolveListingsForProducts(
                $relatedProductIds->toArray(), $cartListingIds,
                $countryId, $currency, $isNawyNow, $country, $wishlistIds,
                self::SECTION_LIMIT,
            );
        });
    }

    private function fromCategoriesSection(
        array $cartCategoryIds, array $cartListingIds,
        string $countryId, string $currency,
        bool $isNawyNow, ?Country $country, array $wishlistIds,
    ): Collection {
        if (empty($cartCategoryIds)) {
            return collect();
        }

        $cacheKey = 'cart_recs:categories:' . md5(implode(',', $cartCategoryIds)) . ":{$countryId}:{$isNawyNow}";

        return $this->cacheRememberCollection($cacheKey, function () use (
            $cartCategoryIds, $cartListingIds, $countryId, $currency,
            $isNawyNow, $country, $wishlistIds,
        ) {
            if ($isNawyNow) {
                return AdminListing::where('country_id', $countryId)
                    ->where('currency', $currency)
                    ->where('status', 'active')
                    ->whereHas('productVariant.product', fn ($q) => $q->whereIn('category_id', $cartCategoryIds))
                    ->whereNotIn('id', $cartListingIds)
                    ->orderByDesc('rating_avg')
                    ->limit(self::SECTION_LIMIT)
                    ->with(self::ADMIN_WITH)
                    ->get()
                    ->map(fn ($l) => $this->toCard($l, $country, $wishlistIds, $isNawyNow))
                    ->filter();
            }

            return VendorListing::where('country_id', $countryId)
                ->where('currency', $currency)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereNotIn('id', $cartListingIds)
                ->whereHas('productVariant.product', fn ($q) => $q
                    ->whereIn('category_id', $cartCategoryIds)
                    ->where('status', 'active'))
                ->orderByDesc('score')
                ->orderByDesc('total_sold')
                ->limit(self::SECTION_LIMIT)
                ->with(self::VENDOR_WITH)
                ->get()
                ->map(fn ($l) => $this->toCard($l, $country, $wishlistIds, $isNawyNow))
                ->filter();
        });
    }

    private function bestSellersSection(
        array $cartListingIds, string $countryId, string $currency,
        bool $isNawyNow, ?Country $country, array $wishlistIds,
    ): Collection {
        $cacheKey = "cart_recs:best_sellers:{$countryId}:{$currency}:{$isNawyNow}";

        return $this->cacheRememberCollection($cacheKey, function () use (
            $cartListingIds, $countryId, $currency,
            $isNawyNow, $country, $wishlistIds,
        ) {
            if ($isNawyNow) {
                return AdminListing::where('country_id', $countryId)
                    ->where('currency', $currency)
                    ->where('status', 'active')
                    ->whereNotIn('id', $cartListingIds)
                    ->orderByDesc('rating_count')
                    ->orderByDesc('rating_avg')
                    ->limit(self::SECTION_LIMIT)
                    ->with(self::ADMIN_WITH)
                    ->get()
                    ->map(fn ($l) => $this->toCard($l, $country, $wishlistIds, $isNawyNow))
                    ->filter();
            }

            return VendorListing::where('country_id', $countryId)
                ->where('currency', $currency)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereNotIn('id', $cartListingIds)
                ->orderByDesc('total_sold')
                ->orderByDesc('score')
                ->limit(self::SECTION_LIMIT)
                ->with(self::VENDOR_WITH)
                ->get()
                ->map(fn ($l) => $this->toCard($l, $country, $wishlistIds, $isNawyNow))
                ->filter();
        });
    }

    private function recentlyViewedSection(
        Customer $customer,
        array $cartProductIds, array $cartListingIds,
        string $countryId, string $currency,
        bool $isNawyNow, ?Country $country, array $wishlistIds,
    ): Collection {
        $recentProductIds = ProductView::where('customer_id', $customer->id)
            ->whereNotIn('product_id', $cartProductIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->take(self::SECTION_LIMIT);

        if ($recentProductIds->isEmpty()) {
            return collect();
        }

        return $this->resolveListingsForProducts(
            $recentProductIds->toArray(), $cartListingIds,
            $countryId, $currency, $isNawyNow, $country, $wishlistIds,
            self::SECTION_LIMIT,
        );
    }

    // ─── Listing resolver ─────────────────────────────────────────────────────

    private function resolveListingsForProducts(
        array $productIds, array $excludeListingIds,
        string $countryId, string $currency,
        bool $isNawyNow, ?Country $country, array $wishlistIds,
        int $limit,
    ): Collection {
        if (empty($productIds)) {
            return collect();
        }

        if ($isNawyNow) {
            return AdminListing::whereHas('productVariant', fn ($q) => $q->whereIn('product_id', $productIds))
                ->where('country_id', $countryId)
                ->where('currency', $currency)
                ->where('status', 'active')
                ->whereNotIn('id', $excludeListingIds)
                ->orderByRaw('score IS NULL, score DESC')
                ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
                ->orderByDesc('rating_count')
                ->orderBy('price')
                ->with(self::ADMIN_WITH)
                ->get()
                ->groupBy(fn ($l) => $l->productVariant->product_id)
                ->map(fn ($group) => $group->first())
                ->values()
                ->take($limit)
                ->map(fn ($l) => $this->toCard($l, $country, $wishlistIds, $isNawyNow))
                ->filter();
        }

        return VendorListing::whereHas('productVariant', fn ($q) => $q->whereIn('product_id', $productIds))
            ->where('country_id', $countryId)
            ->where('currency', $currency)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $excludeListingIds)
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->with(self::VENDOR_WITH)
            ->get()
            ->groupBy(fn ($l) => $l->productVariant->product_id)
            ->map(fn ($group) => $group->first())
            ->values()
            ->take($limit)
            ->map(fn ($l) => $this->toCard($l, $country, $wishlistIds, $isNawyNow))
            ->filter();
    }

    // ─── Card shape dispatch ──────────────────────────────────────────────────

    private function toCard(
        VendorListing|AdminListing $listing,
        ?Country $country,
        array $wishlistIds,
        bool $isNawyNow,
    ): ?array {
        $product = $listing->productVariant?->product;

        if ($product === null || $country === null) {
            return null;
        }

        if ($isNawyNow || $listing instanceof AdminListing) {
            return $this->listingQuery->toAdminCardShape(
                listing:      $listing,
                product:      $product,
                country:      $country,
                isWishlisted: in_array($listing->id, $wishlistIds),
            );
        }

        return $this->listingQuery->toCardShape(
            listing:      $listing,
            product:      $product,
            country:      $country,
            isWishlisted: in_array($listing->id, $wishlistIds),
        );
    }

    // ─── Cart extraction helpers ──────────────────────────────────────────────

    private function extractListingIds(Collection $items, bool $isNawyNow): array
    {
        if ($isNawyNow) {
            return $items->pluck('admin_listing_id')->filter()->unique()->values()->toArray();
        }

        return $items->pluck('vendor_listing_id')->filter()->unique()->values()->toArray();
    }

    private function extractProductIds(Collection $items, bool $isNawyNow): array
    {
        $listingIds = $this->extractListingIds($items, $isNawyNow);

        if (empty($listingIds)) {
            return [];
        }

        if ($isNawyNow) {
            return AdminListing::whereIn('id', $listingIds)
                ->with('productVariant:id,product_id')
                ->get()
                ->pluck('productVariant.product_id')
                ->filter()->unique()->values()->toArray();
        }

        return VendorListing::whereIn('id', $listingIds)
            ->with('productVariant:id,product_id')
            ->get()
            ->pluck('productVariant.product_id')
            ->filter()->unique()->values()->toArray();
    }

    private function extractCategoryIds(Collection $items, bool $isNawyNow): array
    {
        $listingIds = $this->extractListingIds($items, $isNawyNow);

        if (empty($listingIds)) {
            return [];
        }

        if ($isNawyNow) {
            return AdminListing::whereIn('id', $listingIds)
                ->with('productVariant.product:id,category_id')
                ->get()
                ->pluck('productVariant.product.category_id')
                ->filter()->unique()->values()->toArray();
        }

        return VendorListing::whereIn('id', $listingIds)
            ->with('productVariant.product:id,category_id')
            ->get()
            ->pluck('productVariant.product.category_id')
            ->filter()->unique()->values()->toArray();
    }

    // ─── Section titles ───────────────────────────────────────────────────────

    private function sectionTitle(string $type, string $lang): string
    {
        return match ("{$type}:{$lang}") {
            'frequently_bought_together:en' => 'Frequently Bought Together',
            'frequently_bought_together:ar' => 'اشترى معاً في أغلب الأحيان',
            'from_your_categories:en'       => 'More From Your Categories',
            'from_your_categories:ar'       => 'المزيد من فئاتك',
            'best_sellers:en'               => 'Best Sellers',
            'best_sellers:ar'               => 'الأكثر مبيعاً',
            'recently_viewed:en'            => 'Recently Viewed',
            'recently_viewed:ar'            => 'شاهدته مؤخراً',
            default                         => $type,
        };
    }
}
