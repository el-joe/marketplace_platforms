<?php

namespace App\Services;

use App\Models\AdminListing;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\FrequentlyBoughtTogetherItem;
use App\Models\ProductImage;
use App\Models\ProductView;
use App\Models\VendorListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CartRecommendationService
{
    private const SECTION_LIMIT = 12;
    private const CACHE_TTL = 300;
    private const SECTIONS_ORDER = [
        'frequently_bought_together',
        'from_your_categories',
        'best_sellers',
        'recently_viewed',
    ];

    public function getRecommendations(Cart $cart, ?Customer $customer, bool $isNawyNow): array
    {
        $cartItems = CartItem::where('cart_id', $cart->id)->get();

        $cartListingIds = $this->extractListingIds($cartItems, $isNawyNow);
        $cartProductIds = $this->extractProductIds($cartItems, $isNawyNow);
        $cartCategoryIds = $this->extractCategoryIds($cartItems, $isNawyNow);
        $countryId = $cart->country_id;
        $currency = $cart->currency;

        $sections = [];

        foreach (self::SECTIONS_ORDER as $type) {
            $listings = match ($type) {
                'frequently_bought_together' => $this->fbtSection(
                    $cartProductIds, $cartListingIds, $countryId, $currency, $isNawyNow
                ),
                'from_your_categories' => $this->fromCategoriesSection(
                    $cartCategoryIds, $cartListingIds, $countryId, $currency, $isNawyNow
                ),
                'best_sellers' => $this->bestSellersSection(
                    $cartListingIds, $countryId, $currency, $isNawyNow
                ),
                'recently_viewed' => $customer
                    ? $this->recentlyViewedSection(
                        $customer, $cartProductIds, $cartListingIds, $countryId, $currency, $isNawyNow
                    )
                    : collect(),
            };

            if ($listings->isNotEmpty()) {
                $sections[] = [
                    'section_type' => $type,
                    'title_en' => $this->sectionTitle($type, 'en'),
                    'title_ar' => $this->sectionTitle($type, 'ar'),
                    'listings' => $listings->values()->toArray(),
                ];
            }
        }

        return $sections;
    }

    private function fbtSection(
        array $cartProductIds,
        array $cartListingIds,
        string $countryId,
        string $currency,
        bool $isNawyNow
    ): Collection {
        if (empty($cartProductIds)) {
            return collect();
        }

        $cacheKey = 'cart_recs:fbt:' . md5(implode(',', $cartProductIds)) . ":{$countryId}:{$isNawyNow}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $cartProductIds, $cartListingIds, $countryId, $currency, $isNawyNow
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
                $relatedProductIds->toArray(),
                $cartListingIds,
                $countryId,
                $currency,
                $isNawyNow,
                self::SECTION_LIMIT
            );
        });
    }

    private function fromCategoriesSection(
        array $cartCategoryIds,
        array $cartListingIds,
        string $countryId,
        string $currency,
        bool $isNawyNow
    ): Collection {
        if (empty($cartCategoryIds)) {
            return collect();
        }

        $cacheKey = 'cart_recs:categories:' . md5(implode(',', $cartCategoryIds)) . ":{$countryId}:{$isNawyNow}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $cartCategoryIds, $cartListingIds, $countryId, $currency, $isNawyNow
        ) {
            if ($isNawyNow) {
                return AdminListing::where('country_id', $countryId)
                    ->where('currency', $currency)
                    ->where('status', 'active')
                    ->whereHas('productVariant.product', fn ($q) => $q->whereIn('category_id', $cartCategoryIds))
                    ->whereNotIn('id', $cartListingIds)
                    ->orderByDesc('rating_avg')
                    ->limit(self::SECTION_LIMIT)
                    ->with('productVariant.product')
                    ->get()
                    ->map(fn ($l) => $this->adminListingCard($l));
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
                ->with(['vendor:id,name', 'productVariant.product'])
                ->get()
                ->map(fn ($l) => $this->vendorListingCard($l));
        });
    }

    private function bestSellersSection(
        array $cartListingIds,
        string $countryId,
        string $currency,
        bool $isNawyNow
    ): Collection {
        $cacheKey = "cart_recs:best_sellers:{$countryId}:{$currency}:{$isNawyNow}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $cartListingIds, $countryId, $currency, $isNawyNow
        ) {
            if ($isNawyNow) {
                return AdminListing::where('country_id', $countryId)
                    ->where('currency', $currency)
                    ->where('status', 'active')
                    ->whereNotIn('id', $cartListingIds)
                    ->orderByDesc('rating_count')
                    ->orderByDesc('rating_avg')
                    ->limit(self::SECTION_LIMIT)
                    ->with('productVariant.product')
                    ->get()
                    ->map(fn ($l) => $this->adminListingCard($l));
            }

            return VendorListing::where('country_id', $countryId)
                ->where('currency', $currency)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereNotIn('id', $cartListingIds)
                ->orderByDesc('total_sold')
                ->orderByDesc('score')
                ->limit(self::SECTION_LIMIT)
                ->with(['vendor:id,name', 'productVariant.product'])
                ->get()
                ->map(fn ($l) => $this->vendorListingCard($l));
        });
    }

    private function recentlyViewedSection(
        Customer $customer,
        array $cartProductIds,
        array $cartListingIds,
        string $countryId,
        string $currency,
        bool $isNawyNow
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
            $recentProductIds->toArray(),
            $cartListingIds,
            $countryId,
            $currency,
            $isNawyNow,
            self::SECTION_LIMIT
        );
    }

    private function resolveListingsForProducts(
        array $productIds,
        array $excludeListingIds,
        string $countryId,
        string $currency,
        bool $isNawyNow,
        int $limit
    ): Collection {
        if (empty($productIds)) {
            return collect();
        }

        if ($isNawyNow) {
            $listings = AdminListing::whereHas('productVariant', fn ($q) => $q
                    ->whereIn('product_id', $productIds))
                ->where('country_id', $countryId)
                ->where('currency', $currency)
                ->where('status', 'active')
                ->whereNotIn('id', $excludeListingIds)
                ->orderByRaw('score IS NULL, score DESC')
                ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
                ->orderByDesc('rating_count')
                ->orderBy('price')
                ->with('productVariant.product')
                ->get()
                ->groupBy(fn ($l) => $l->productVariant->product_id)
                ->map(fn ($group) => $group->first())
                ->values()
                ->take($limit);

            return $listings->map(fn ($l) => $this->adminListingCard($l));
        }

        $listings = VendorListing::whereHas('productVariant', fn ($q) => $q
                ->whereIn('product_id', $productIds))
            ->where('country_id', $countryId)
            ->where('currency', $currency)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $excludeListingIds)
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->with(['vendor:id,name', 'productVariant.product'])
            ->get()
            ->groupBy(fn ($l) => $l->productVariant->product_id)
            ->map(fn ($group) => $group->first())
            ->values()
            ->take($limit);

        return $listings->map(fn ($l) => $this->vendorListingCard($l));
    }

    private function vendorListingCard(VendorListing $listing): array
    {
        $variant = $listing->productVariant;
        $product = $variant?->product;

        return [
            'id' => $listing->id,
            'listing_type' => 'vendor_listing',
            'price' => $listing->price,
            'compare_at_price' => $listing->compare_at_price,
            'currency' => $listing->currency,
            'condition' => $listing->condition,
            'rating_avg' => $listing->rating_avg,
            'rating_count' => $listing->rating_count,
            'vendor_covers_delivery' => (bool) $listing->vendor_covers_delivery,
            'vendor' => $listing->vendor
                ? ['id' => $listing->vendor->id, 'name' => $listing->vendor->name]
                : null,
            'product_variant' => $variant ? [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name_en' => $variant->name_en,
                'name_ar' => $variant->name_ar,
            ] : null,
            'product' => $product ? [
                'id' => $product->id,
                'name_en' => $product->name_en,
                'name_ar' => $product->name_ar,
                'slug' => $product->slug,
            ] : null,
            'primary_image_url' => $this->primaryImage($product),
        ];
    }

    private function adminListingCard(AdminListing $listing): array
    {
        $variant = $listing->productVariant;
        $product = $variant?->product;

        return [
            'id' => $listing->id,
            'listing_type' => 'admin_listing',
            'price' => $listing->getRawOriginal('price'),
            'compare_at_price' => null,
            'currency' => $listing->currency,
            'shipping_cost' => $listing->shipping_cost,
            'fulfillment_type' => $listing->fulfillment_type,
            'rating_avg' => $listing->rating_avg,
            'rating_count' => $listing->rating_count,
            'product_variant' => $variant ? [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name_en' => $variant->name_en,
                'name_ar' => $variant->name_ar,
            ] : null,
            'product' => $product ? [
                'id' => $product->id,
                'name_en' => $product->name_en,
                'name_ar' => $product->name_ar,
                'slug' => $product->slug,
            ] : null,
            'primary_image_url' => $this->primaryImage($product),
        ];
    }

    private function primaryImage(?object $product): ?string
    {
        if (!$product) {
            return null;
        }

        $image = ProductImage::where('product_id', $product->id)
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->first();

        return $image?->url;
    }

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

    private function sectionTitle(string $type, string $lang): string
    {
        return match ("{$type}:{$lang}") {
            'frequently_bought_together:en' => 'Frequently Bought Together',
            'frequently_bought_together:ar' => 'اشترى معاً في أغلب الأحيان',
            'from_your_categories:en' => 'More From Your Categories',
            'from_your_categories:ar' => 'المزيد من فئاتك',
            'best_sellers:en' => 'Best Sellers',
            'best_sellers:ar' => 'الأكثر مبيعاً',
            'recently_viewed:en' => 'Recently Viewed',
            'recently_viewed:ar' => 'شاهدته مؤخراً',
            default => $type,
        };
    }
}
