<?php

namespace App\Services\Customer;

use App\Http\Resources\Customer\ProductListResource;
use App\Models\Attribute;
use App\Models\Country;
use App\Models\WishlistItem;
use App\Services\FlashSaleService;
use App\Services\Shared\PageBuilderService;
use App\Support\Bilingual;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-19: rewritten to a two-phase query against the
 * `product_country_buybox` read model (maintained by BuyBoxRebuildService)
 * instead of ~20 selectRaw COALESCE-of-3-correlated-subqueries expressions
 * evaluated over the whole grouped product set before LIMIT.
 *
 * Phase 1 (idQuery/applyFilters/applySort): a lean query against the
 * indexed buy-box table returns product_id + the row's own pre-aggregated
 * columns for exactly one page of results (LIMIT applied before any
 * per-row hydration work).
 *
 * Phase 2 (buildProductsPayload): batch-loads everything the response
 * needs for that page only — shipping badge + category name + buy-box
 * variant slug/name are pulled in via LEFT JOINs on the already-limited
 * phase-1 result (cheap, because it is at most $perPage rows), and images
 * come from ListingImageResolver (P-17).
 */
class ProductQueryService
{
    public function __construct(
        private readonly SponsoredProductService $sponsored,
        private readonly \App\Services\Media\ListingImageResolver $imageResolver,
        private readonly PageBuilderService $pageBuilder,
        private readonly FlashSaleService $flashSale,
    ) {
    }

    /**
     * Paginate products with optional category scope.
     *
     * @param  array<string,mixed>  $filters
     * @param  list<string>|null  $categoryIds  When provided, restricts to these category IDs
     */
    public function paginate(
        Country $country,
        array $filters,
        int $perPage = 20,
        ?array $categoryIds = null,
    ): LengthAwarePaginator {
        if ($categoryIds === null && !empty($filters['category'])) {
            $categoryIds = app(CategoryService::class)->getCategoryIdsForFilter($filters['category']);
        }

        $builder = $this->baseQuery($country);

        if ($categoryIds !== null) {
            $builder->whereIn('bb.category_id', $categoryIds);
        }

        $builder = $this->applyFilters($builder, $filters, $categoryIds);
        $builder = $this->applySort($builder, $filters['sort'] ?? 'relevance');

        $page = Paginator::resolveCurrentPage();
        $total = (clone $builder)->distinct()->count('bb.product_id');

        $rows = (clone $builder)
            ->select($this->rowColumns())
            ->forPage($page, $perPage)
            ->get();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * Return price-range facets for the current filter set.
     *
     * @param  array<string,mixed>  $filters
     * @param  list<string>|null  $categoryIds
     */
    public function facets(Country $country, array $filters, ?array $categoryIds = null): array
    {
        if ($categoryIds === null && !empty($filters['category'])) {
            $categoryIds = app(CategoryService::class)->getCategoryIdsForFilter($filters['category']);
        }

        $base = $this->baseQuery($country);

        if ($categoryIds !== null) {
            $base->whereIn('bb.category_id', $categoryIds);
        }

        $base = $this->applyFilters($base, $filters, $categoryIds);

        $priceRange = (clone $base)
            ->selectRaw('MIN(bb.min_price) as low, MAX(bb.max_price) as high')
            ->first();

        return [
            'price_range' => [
                'min' => $priceRange ? (int) $priceRange->low : 0,
                'max' => $priceRange ? (int) $priceRange->high : 0,
            ],
            'attributes' => $this->attributeFacets($base, $categoryIds ?? []),
        ];
    }

    /**
     * Filterable attributes for the given categories, with per-value product counts
     * scoped to the already-filtered product set in $base.
     * One query for the candidate product ids, one for the attributes (+ their
     * values, eager-loaded), one grouped query for every attribute's value
     * counts at once — never one query per attribute.
     *
     * @param  list<string>  $categoryIds
     */
    private function attributeFacets($base, array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $productIds = (clone $base)->distinct()->pluck('bb.product_id');

        if ($productIds->isEmpty()) {
            return [];
        }

        $attributes = Attribute::query()
            ->where('is_filterable', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with('values')
            ->orderBy('sort_order')
            ->get();

        if ($attributes->isEmpty()) {
            return [];
        }

        $counts = DB::table('product_variant_attributes as pva')
            ->join('product_variants as pv', 'pv.id', '=', 'pva.product_variant_id')
            ->whereIn('pv.product_id', $productIds)
            ->whereIn('pva.attribute_id', $attributes->pluck('id'))
            ->selectRaw('pva.attribute_id, pva.attribute_value_id, COUNT(DISTINCT pv.product_id) as cnt')
            ->groupBy('pva.attribute_id', 'pva.attribute_value_id')
            ->get()
            ->groupBy('attribute_id');

        return $attributes->map(function (Attribute $attribute) use ($counts) {
            $attrCounts = ($counts->get($attribute->id) ?? collect())->pluck('cnt', 'attribute_value_id');

            return [
                'id'     => $attribute->id,
                'code'   => $attribute->code,
                'name'   => Bilingual::pair($attribute, 'name'),
                'type'   => $attribute->type->value,
                'unit'   => $attribute->unit,
                'values' => $attribute->values->map(fn ($value) => [
                    'id'        => $value->id,
                    'value'     => Bilingual::pair($value, 'value'),
                    'color_hex' => $value->color_hex,
                    'count'     => (int) ($attrCounts[$value->id] ?? 0),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Convert a paginator into the standard products payload:
     * { items, meta } — with wishlist flags and sponsored injection applied.
     */
    public function buildProductsPayload(
        LengthAwarePaginator $paginator,
        Country $country,
        int $page,
        string $placement = 'category_top',
        array $categoryIds = [],
        array $attributeFilters = [],
    ): array {
        $wishlistIds = $this->wishlistIds();

        $rows = collect($paginator->items());

        // Batched, not per-item: two queries total for every buy-box variant
        // on this page (ListingImageResolver — enhancement.md P-17 task 4),
        // instead of the removed correlated per-row image subqueries.
        $variantIds = $rows->pluck('buy_box_variant_id')->filter()->unique()->values();
        $imagesByVariant = $this->imageResolver->forVariants($variantIds);

        $productIds = $rows->pluck('id')->filter()->unique()->values();
        $promoBadges = PromoBadgeResolver::instance()->resolve(
            $rows->map(fn ($r) => [$r->buy_box_listing_type ?? null, $r->buy_box_listing_id ?? null, $r->id])
        );
        $megaDealProductIds = $this->pageBuilder->activeMegaDealProductIds($productIds, $country);
        $flashSaleEndsAtByProduct = $this->flashSale->activeFlashSaleEndsAtByProduct($productIds, $country);

        $items = ProductListResource::collection($rows)
            ->map(function (ProductListResource $r) use ($wishlistIds, $imagesByVariant, $promoBadges, $megaDealProductIds, $flashSaleEndsAtByProduct) {
                $r->resource->is_sponsored = false;
                $r->resource->is_wishlisted = in_array($r->resource->id, $wishlistIds);
                $r->resource->resolved_images = $imagesByVariant[$r->resource->buy_box_variant_id] ?? [];
                $r->resource->promo_badges = $promoBadges[PromoBadgeResolver::key($r->resource->buy_box_listing_type ?? null, $r->resource->buy_box_listing_id ?? null, $r->resource->id)] ?? [];
                $flashSaleEndsAt = $flashSaleEndsAtByProduct->get($r->resource->id);
                // Flash sale takes precedence over mega deal when both apply
                // (edge case) — a product never shows both badges.
                $r->resource->is_flash_sale = $flashSaleEndsAt !== null;
                $r->resource->flash_sale_ends_at = $flashSaleEndsAt?->toISOString();
                $r->resource->is_mega_deal = $flashSaleEndsAt === null && $megaDealProductIds->contains($r->resource->id);
                return $r->toArray(request());
            })
            ->toArray();

        $items = $this->sponsored->inject($items, $country, $page, $placement, null, $categoryIds, $attributeFilters);

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    // ─── Query building ───────────────────────────────────────────────────────

    /**
     * Phase 1: the lean, indexed query — buy-box read model joined only to
     * `products` (to exclude products that went inactive/were soft-deleted
     * since the last buy-box rebuild; the buy-box row itself is otherwise
     * only refreshed by the observers/listener wired to it). No listing
     * tables, no correlated subqueries, no aggregation.
     */
    public function baseQuery(Country $country)
    {
        return DB::table('product_country_buybox as bb')
            ->join('products as p', 'p.id', '=', 'bb.product_id')
            ->where('bb.country_id', $country->id)
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at');
    }

    /**
     * The full column set for a page of results, joined on the already
     * page-limited candidate set so these joins run against at most
     * $perPage rows — never against the whole filtered/grouped set like
     * the old correlated subqueries did.
     */
    private function rowColumns(): array
    {
        return [
            'bb.product_id as id',
            'p.name_en', 'p.name_ar', 'p.slug', 'p.is_featured', 'p.published_at',
            'pcs.name_override_en', 'pcs.name_override_ar',
            'bb.min_price', 'bb.max_price',
            'bb.seller_count as active_seller_count',
            'bb.admin_listing_count',
            'bb.total_stock',
            'bb.rating_avg', 'bb.rating_count',
            'bb.listing_id as buy_box_listing_id',
            'bb.listing_type as buy_box_listing_type',
            'bb.variant_id as buy_box_variant_id',
            'bb.compare_at_price as buy_box_compare_at_price',
            'pv.slug as buy_box_variant_slug',
            'pv.variant_name as buy_box_variant_name',
            'pv.variant_name_ar as buy_box_variant_name_ar',
            'cat.name_en as category_name_en',
            'cat.name_ar as category_name_ar',
            'sm.badge_label_en as buy_box_shipping_label_en',
            'sm.badge_label_ar as buy_box_shipping_label_ar',
            'sm.badge_color_hex as buy_box_shipping_color_hex',
            'sm.badge_text_color_hex as buy_box_shipping_text_color_hex',
            'sm.min_delivery_days as buy_box_shipping_days_min',
            'sm.max_delivery_days as buy_box_shipping_days_max',
            'sm.badge_image_path as buy_box_shipping_badge_image_path',
            'bb.is_express as buy_box_shipping_is_express',
        ];
    }

    /**
     * @param  list<string>|null  $categoryIds  Pre-resolved category IDs (category subtree, or
     *                                           union of subtrees for a custom page). When omitted,
     *                                           resolved from $filters['category'] (id or slug).
     */
    public function applyFilters($builder, array $filters, ?array $categoryIds = null)
    {
        $builder->leftJoin('categories as cat', 'cat.id', '=', 'bb.category_id')
            ->leftJoin('product_country_settings as pcs', function ($j) {
                $j->on('pcs.product_id', '=', 'bb.product_id')
                    ->on('pcs.country_id', '=', 'bb.country_id')
                    ->where('pcs.is_available', true);
            })
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'bb.variant_id')
            ->leftJoin('shipping_methods as sm', 'sm.id', '=', 'bb.shipping_method_id');

        if (!empty($filters['category'])) {
            $categoryIds ??= app(CategoryService::class)->getCategoryIdsForFilter($filters['category']);
            $builder->whereIn('bb.category_id', $categoryIds);
        }
        if (!empty($filters['brand'])) {
            $builder->where('bb.brand_id', $filters['brand']);
        }
        if (!empty($filters['price_min'])) {
            $builder->where('bb.min_price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $builder->where('bb.max_price', '<=', (int) $filters['price_max']);
        }
        if (!empty($filters['rating_min'])) {
            $builder->where('bb.rating_avg', '>=', $filters['rating_min']);
        }
        if (!empty($filters['condition'])) {
            // Aggregates (min/max price, rating, stock) never depend on this
            // filter (enhancement.md P-19: fixing that dependency was the point) —
            // it only narrows which products qualify, via the winning listing's
            // own condition when the winner is a vendor listing.
            $builder->whereExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('vendor_listings as vl_cond')
                    ->whereColumn('vl_cond.id', 'bb.listing_id')
                    ->where('vl_cond.condition', $filters['condition']);
            });
        }
        if (!empty($filters['fulfillment_model'])) {
            $builder->where('bb.fulfillment_model', $filters['fulfillment_model']);
        }
        if (empty($filters['include_oos'])) {
            $builder->where('bb.total_stock', '>', 0);
        }
        if (!empty($filters['attributes']) && is_array($filters['attributes'])) {
            foreach ($filters['attributes'] as $attrCode => $values) {
                $values = (array) $values;
                $builder->whereExists(function ($sub) use ($attrCode, $values) {
                    $sub->select(DB::raw(1))
                        ->from('product_variant_attributes as pva')
                        ->join('product_variants as pv_attr', 'pv_attr.id', '=', 'pva.product_variant_id')
                        ->join('attributes as a', 'a.id', '=', 'pva.attribute_id')
                        ->join('attribute_values as av', 'av.id', '=', 'pva.attribute_value_id')
                        ->whereColumn('pv_attr.product_id', 'bb.product_id')
                        ->where('a.code', $attrCode)
                        ->whereIn('av.value_en', $values);
                });
            }
        }

        return $builder;
    }

    public function applySort($builder, string $sort)
    {
        return match ($sort) {
            // Explicit customer-requested sorts always win — no ad-package
            // boost applied here (FIX-H2 acceptance test: boosted position
            // must not change when price/newest/rating is requested).
            'price_asc' => $builder->orderBy('bb.min_price', 'asc'),
            'price_desc' => $builder->orderBy('bb.max_price', 'desc'),
            'rating' => $builder->orderBy('bb.rating_avg', 'desc'),
            'newest' => $builder->orderBy('p.published_at', 'desc'),
            'best_selling' => $builder->orderBy('bb.total_sold', 'desc'),
            // Default/relevance sort only: active-subscription listings are
            // boosted ahead of non-boosted ones, ranked by slot tier
            // (popup slot > plain promotion slot), before falling back to the
            // pre-existing featured/rating tiebreakers.
            default => $builder->orderByRaw(
                "(SELECT COALESCE(MAX(CASE WHEN pas.shows_popup = 1 THEN 2 ELSE 1 END), 0)
                    FROM paid_ad_bookings pab
                    JOIN paid_ad_slots pas ON pas.id = pab.paid_ad_slot_id
                    JOIN paid_ad_creatives pac ON pac.paid_ad_booking_id = pab.id AND pac.is_current = 1
                    WHERE bb.listing_type = 'vendor'
                      AND pac.destination_reference_id = bb.listing_id
                      AND pab.status = 'active'
                      AND pas.target_type = 'listing_promotion'
                      AND (pab.booked_until IS NULL OR pab.booked_until >= ?)) DESC",
                [now()->toDateString()]
            )
                ->orderBy('p.is_featured', 'desc')
                ->orderBy('bb.rating_avg', 'desc'),
        };
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function wishlistIds(): array
    {
        $customerId = auth('customer')->id();
        if (!$customerId) {
            return [];
        }

        $vendorProductIds = WishlistItem::where('wishlist_items.customer_id', $customerId)
            ->join('vendor_listings', 'vendor_listings.id', '=', 'wishlist_items.vendor_listing_id')
            ->join('product_variants', 'product_variants.id', '=', 'vendor_listings.product_variant_id')
            ->pluck('product_variants.product_id');

        $adminProductIds = WishlistItem::where('wishlist_items.customer_id', $customerId)
            ->join('admin_listings', 'admin_listings.id', '=', 'wishlist_items.admin_listing_id')
            ->join('product_variants', 'product_variants.id', '=', 'admin_listings.product_variant_id')
            ->pluck('product_variants.product_id');

        return $vendorProductIds->merge($adminProductIds)->unique()->toArray();
    }
}
