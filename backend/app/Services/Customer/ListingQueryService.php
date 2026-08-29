<?php

namespace App\Services\Customer;

use App\Enums\ClassifiedListingStatus;
use App\Enums\GlobalSystemType;
use App\Enums\ProductStatus;
use App\Enums\TravelPackageStatus;
use App\Enums\VendorGlobalStatus;
use App\Enums\VendorListingStatus;
use App\Models\AdminListing;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Product;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListingQueryService
{
    /**
     * Base listing query for a category (+ descendants) grid: active listings,
     * active vendor, active product within the given category IDs.
     *
     * @param  list<string>  $categoryIds
     */
    public function baseCategoryQuery(Country $country, array $categoryIds)
    {
        return VendorListing::where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas(
                'productVariant.product',
                fn($q) => $q->whereIn('category_id', $categoryIds)->where('status', ProductStatus::Active->value),
            )
            ->whereHas('vendor', fn($q) => $q->where('global_status', VendorGlobalStatus::Active->value));
    }

    /**
     * Base listing query for text search: active listings whose product matches
     * the query, active vendor. Kept listing-centric so the same product sold by
     * multiple vendors appears once per vendor listing, not deduped per product.
     */
    public function baseSearchQuery(Country $country, string $query)
    {
        return VendorListing::where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('productVariant.product', function ($q) use ($query) {
                $q->where('status', ProductStatus::Active->value)
                    ->where(function ($q2) use ($query) {
                        $q2->where('name_en', 'like', "%{$query}%")
                            ->orWhere('name_ar', 'like', "%{$query}%")
                            ->orWhere('short_desc_en', 'like', "%{$query}%")
                            ->orWhere('model_number', 'like', "%{$query}%");
                    });
            })
            ->whereHas('vendor', fn($q) => $q->where('global_status', VendorGlobalStatus::Active->value));
    }

    /**
     * Apply the standard grid filters (price, brand, rating, condition, fulfillment,
     * stock, attributes) to a VendorListing query built from baseCategoryQuery().
     *
     * @param  array<string,mixed>  $filters
     */
    public function applyFilters($builder, array $filters)
    {
        if (!empty($filters['category'])) {
            $builder->whereHas('productVariant.product', fn($q) => $q->where('category_id', $filters['category']));
        }
        if (!empty($filters['brand'])) {
            $builder->whereHas('productVariant.product', fn($q) => $q->where('brand_id', $filters['brand']));
        }
        if (!empty($filters['price_min'])) {
            $builder->where('price', '>=', (int) ($filters['price_min'] * 100));
        }
        if (!empty($filters['price_max'])) {
            $builder->where('price', '<=', (int) ($filters['price_max'] * 100));
        }
        if (!empty($filters['rating_min'])) {
            $builder->where('rating_avg', '>=', $filters['rating_min']);
        }
        if (!empty($filters['condition'])) {
            $builder->where('condition', $filters['condition']);
        }
        if (!empty($filters['fulfillment_model'])) {
            $builder->where('fulfillment_model', $filters['fulfillment_model']);
        }
        if (empty($filters['include_oos'])) {
            $builder->whereHas('warehouseInventories', fn($q) => $q->where('quantity_available', '>', 0));
        }
        if (!empty($filters['attributes']) && is_array($filters['attributes'])) {
            foreach ($filters['attributes'] as $attrCode => $values) {
                $values = (array) $values;
                $builder->whereExists(function ($sub) use ($attrCode, $values) {
                    $sub->select(DB::raw(1))
                        ->from('product_variant_attributes as pva')
                        ->join('attributes as a', 'a.id', '=', 'pva.attribute_id')
                        ->join('attribute_values as av', 'av.id', '=', 'pva.attribute_value_id')
                        ->whereColumn('pva.product_variant_id', 'vendor_listings.product_variant_id')
                        ->where('a.code', $attrCode)
                        ->whereIn('av.value_en', $values);
                });
            }
        }

        return $builder;
    }

    /**
     * Apply sort to a VendorListing category query, falling back to the default
     * buy-box ordering (express_fbn/merchant_fbp/marketplace, then price).
     */
    public function applySort($builder, string $sort)
    {
        return match ($sort) {
            'price_asc' => $builder->orderBy('price', 'asc'),
            'price_desc' => $builder->orderBy('price', 'desc'),
            'rating' => $builder->orderByDesc('rating_avg'),
            'newest' => $builder->orderByDesc('created_at'),
            'best_selling' => $builder->orderByDesc('total_sold'),
            default => $builder
                ->orderByRaw('score IS NULL, score DESC')
                ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
                ->orderByDesc('rating_count')
                ->orderBy('price'),
        };
    }

    /**
     * Paginate category listings with filters, sort, and eager loads applied.
     *
     * @param  list<string>  $categoryIds
     * @param  array<string,mixed>  $filters
     */
    public function paginateForCategory(
        Country $country,
        array $categoryIds,
        array $filters,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $builder = $this->baseCategoryQuery($country, $categoryIds)
            ->with([
                'vendor:id,store_name,store_rating_avg',
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images',
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ]);

        $builder = $this->applyFilters($builder, $filters);
        $builder = $this->applySort($builder, $filters['sort'] ?? 'relevance');

        return $builder->paginate($perPage);
    }

    /**
     * Resolve the best buy-box listing for a product variant in a country.
     * Admin listings (Noon Express / platform stock) always win over vendor listings.
     *
     * Returns an object with:
     *   ->id             listing UUID
     *   ->price          BIGINT base-currency
     *   ->listing_type   'admin' | 'vendor'
     *   ->vendor         Vendor|null
     *   ->primaryShippingMethod  ShippingMethod|null
     *   ->productVariant ProductVariant
     */
    public function getForVariant(
        string $productVariantId,
        Country $country,
        int $limit = 10,
    ): Collection {
        // 1. Check admin listing first (platform stock always wins)
        $admin = AdminListing::query()
            ->where('product_variant_id', $productVariantId)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with([
                'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
                'productVariant:id,sku',
            ])
            ->orderBy('price')
            ->get()
            ->map(function (AdminListing $al) {
                $al->setAttribute('listing_type', 'admin');
                $al->setAttribute('vendor', null);
                return $al;
            });

        if ($admin->isNotEmpty()) {
            return $admin->take($limit);
        }

        // 2. Fall back to vendor listings
        return VendorListing::query()
            ->where('product_variant_id', $productVariantId)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('vendor', fn($q) => $q->where('global_status', VendorGlobalStatus::Active->value))
            ->with([
                'vendor:id,store_name,store_rating_avg,store_rating_count',
                'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
                'productVariant:id,sku',
            ])
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->limit($limit)
            ->get()
            ->map(function (VendorListing $vl) {
                $vl->setAttribute('listing_type', 'vendor');
                return $vl;
            });
    }

    /**
     * Resolve the best buy-box listing for each product in a collection.
     * AdminListing (platform/Express FBN) always wins over VendorListing.
     *
     * Returns array keyed by product_id → VendorListing|AdminListing|null
     */
    public function getBuyBoxForProducts(
        Collection $products,
        Country $country,
    ): array {
        $productIds = $products->pluck('id')->all();

        // Collect all variant IDs for these products
        $variantMap = [];  // variant_id => product_id
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $variantMap[$variant->id] = $product->id;
            }
        }
        $variantIds = array_keys($variantMap);

        if (empty($variantIds)) {
            return array_fill_keys($productIds, null);
        }

        // ── Admin listings (platform stock — always first) ────────────────────
        $adminListings = AdminListing::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with([
                'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images',
                'productVariant.product.brand',
            ])
            ->orderBy('price')
            ->get()
            ->groupBy('product_variant_id');

        // ── Vendor listings (fallback) ─────────────────────────────────────────
        $vendorListings = VendorListing::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('vendor', fn ($q) => $q->where('global_status', VendorGlobalStatus::Active->value))
            ->with([
                'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
                'vendor:id,store_name,store_rating_avg',
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images',
                'productVariant.product.brand',
            ])
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->get()
            ->groupBy('product_variant_id');

        // ── Build result: admin wins per product, fall back to vendor ──────────
        $result = [];

        foreach ($products as $product) {
            $bestListing = null;

            foreach ($product->variants as $variant) {
                // Admin first
                if ($adminListings->has($variant->id)) {
                    $bestListing = $adminListings->get($variant->id)->first();
                    break;
                }
            }

            if ($bestListing === null) {
                foreach ($product->variants as $variant) {
                    if ($vendorListings->has($variant->id)) {
                        $bestListing = $vendorListings->get($variant->id)->first();
                        break;
                    }
                }
            }

            $result[$product->id] = $bestListing;
        }

        return $result;
    }

    /**
     * Canonical listing card shape returned by EVERY listing grid endpoint.
     * All grid APIs must use this method — never build listing card arrays inline.
     */
    public function toCardShape(
        VendorListing $listing,
        Product $product,
        Country $country,
        bool $isWishlisted = false,
        bool $isSponsored = false,
    ): array {
        $variant = $listing->productVariant;
        $variantImage = $variant->images->first()?->url ?? $product->images->first()?->url ?? null;
        $imagesSlider = $this->buildImagesSlider($variant, $product);

        $url = route('customer.listing.show', [$country->site_code, $variant->id .'--' . $listing->id]);
        $url_param = $variant->id .'--' . $listing->id;


        return [
            'listing_id' => $listing->id,
            'listing_type' => $listing->global_system_type === GlobalSystemType::ExpressFbn ? 'admin' : 'vendor',
            'listing_ref' => app(\App\Services\Customer\ListingIdentifierService::class)->buildListingRef($listing),
            'sku' => $variant->sku,
            'vendor_sku' => $listing->vendor_sku,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'slug' => $product->slug,
            'variant_id' => $variant->id,
            'variant_slug' => $variant->slug,
            'product_url' => $url,
            'url_param' => $url_param,
            'variant_name' => $variant->variant_name ?? $variant->sku,
            'variant_image' => $variantImage,
            'primary_image' => $variantImage,
            'images' => $imagesSlider,
            'name_en' => $product->name_en,
            'name_ar' => $product->name_ar,
            'thumbnail' => $product->images->first()?->url ?? null,
            'category_name' => [
                'en' => $product->category?->name_en,
                'ar' => $product->category?->name_ar,
            ],
            'brand' => $product->brand ? [
                'id'       => $product->brand->id,
                'name'     => ['ar' => $product->brand->name_ar, 'en' => $product->brand->name_en],
                'slug'     => $product->brand->slug,
                'logo_url' => $product->brand->logo_url,
            ] : null,
            'price' => $listing->price,
            'price_formatted' => number_format($listing->price / 100, 2),
            'compare_at_price' => $listing->compare_at_price ?? null,
            'currency' => $country->currency_code,
            'condition' => $listing->condition,
            'is_admin_listing' => $listing->global_system_type === GlobalSystemType::ExpressFbn,
            'is_express_fbn' => $listing->global_system_type === GlobalSystemType::ExpressFbn,
            'fulfillment_model' => $listing->fulfillment_model,
            'vendor' => [
                'id' => $listing->vendor->id,
                'store_name' => $listing->vendor->store_name,
                'rating' => $listing->vendor->store_rating_avg,
            ],
            'shipping_badge' => $listing->primaryShippingMethod ? [
                'label'            => [
                    'ar' => $listing->primaryShippingMethod->badge_label_ar,
                    'en' => $listing->primaryShippingMethod->badge_label_en,
                ],
                'color_hex'        => $listing->primaryShippingMethod->badge_color_hex,
                'text_color_hex'   => $listing->primaryShippingMethod->badge_text_color_hex,
                'badge_image_url'  => $listing->primaryShippingMethod->badge_image_url,
                'delivery_days_min' => $listing->primaryShippingMethod->min_delivery_days,
                'delivery_days_max' => $listing->primaryShippingMethod->max_delivery_days,
                'is_express'       => (bool) $listing->primaryShippingMethod->is_express_type,
            ] : null,
            'rating_avg' => $listing->rating_avg,
            'rating_count' => $listing->rating_count,
            'total_sold' => $listing->total_sold,
            'is_wishlisted' => $isWishlisted,
            'is_sponsored' => $isSponsored,
        ];
    }

    /**
     * Builds the ordered image slider array for a variant/product pair:
     * variant-specific images first, then product-level images (no variant FK).
     */
    private function buildImagesSlider($variant, Product $product): array
    {
        $variantImages = $variant->images
            ->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_primary' => (bool) $img->is_primary,
                'position'   => (int) $img->position,
                'variant_id' => $img->product_variant_id,
            ])->values()->all();

        $productImages = $product->images
            ->filter(fn ($img) => $img->product_variant_id === null)
            ->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_primary' => (bool) $img->is_primary,
                'position'   => (int) $img->position,
                'variant_id' => null,
            ])->values()->all();

        return array_values(array_merge($variantImages, $productImages));
    }

    /**
     * Shape an AdminListing into the same card shape as toCardShape().
     * listing_type = 'admin', vendor = null (platform sells directly).
     */
    public function toAdminCardShape(
        AdminListing $listing,
        Product $product,
        Country $country,
        bool $isWishlisted = false,
    ): array {
        $variant = $listing->productVariant;
        $variantImage = $variant->images->first()?->url ?? $product->images->first()?->url ?? null;
        $imagesSlider = $this->buildImagesSlider($variant, $product);

        return [
            'listing_id'       => $listing->id,
            'listing_type'     => 'admin',
            'listing_ref'      => app(ListingIdentifierService::class)->buildListingRef($listing),
            'sku'              => $variant->sku,
            'vendor_sku'       => null,
            'admin_sku'        => $listing->platform_sku ?? $variant->sku,
            'product_id'       => $product->id,
            'product_slug'     => $product->slug,
            'slug'             => $product->slug,
            'variant_id'       => $variant->id,
            'variant_slug'     => $variant->slug,
            'variant_name'     => $variant->variant_name ?? $variant->sku,
            'variant_image'    => $variantImage,
            'primary_image'    => $variantImage,
            'images'           => $imagesSlider,
            'product_url'      => "/products/p-{$listing->id}",
            'url_param'        => "p-{$listing->id}",
            'name'             => ['ar' => $product->name_ar, 'en' => $product->name_en],
            'name_ar'          => $product->name_ar,
            'name_en'          => $product->name_en,
            'category_name'    => [
                'en' => $product->category?->name_en,
                'ar' => $product->category?->name_ar,
            ],
            'brand'            => $product->brand ? [
                'id'       => $product->brand->id,
                'name'     => ['ar' => $product->brand->name_ar, 'en' => $product->brand->name_en],
                'slug'     => $product->brand->slug,
                'logo_url' => $product->brand->logo_url,
            ] : null,
            'price'            => $listing->price,
            'price_formatted'  => number_format($listing->price / 100, 2),
            'compare_at_price' => $listing->compare_at_price ?? null,
            'currency'         => $country->currency_code,
            'condition'        => $listing->condition,
            'is_admin_listing' => true,
            'is_express_fbn'   => true,
            'fulfillment_model'=> $listing->fulfillment_model ?? 'fbn',
            'express_badge'    => [
                'label' => ['ar' => $listing->express_badge_label_ar, 'en' => $listing->express_badge_label_en],
            ],
            'sold_by'          => ['ar' => $listing->sold_by_label_ar, 'en' => $listing->sold_by_label_en],
            'vendor'           => null,
            'rating_avg'       => (float) $listing->rating_avg,
            'rating_count'     => (int) $listing->rating_count,
            'is_wishlisted'    => $isWishlisted,
            'is_sponsored'     => false,
            'shipping_badge'   => $listing->primaryShippingMethod ? [
                'label'            => [
                    'ar' => $listing->primaryShippingMethod->badge_label_ar,
                    'en' => $listing->primaryShippingMethod->badge_label_en,
                ],
                'color_hex'        => $listing->primaryShippingMethod->badge_color_hex,
                'text_color_hex'   => $listing->primaryShippingMethod->badge_text_color_hex,
                'badge_image_url'  => $listing->primaryShippingMethod->badge_image_url,
                'delivery_days_min' => $listing->primaryShippingMethod->min_delivery_days,
                'delivery_days_max' => $listing->primaryShippingMethod->max_delivery_days,
                'is_express'       => (bool) $listing->primaryShippingMethod->is_express_type,
            ] : null,
        ];
    }

    /**
     * Dispatch to toCardShape() or toAdminCardShape() based on listing type.
     *
     * @param VendorListing|AdminListing $listing
     */
    public function toMixedCardShape(
        VendorListing|AdminListing $listing,
        Product $product,
        Country $country,
        bool $isWishlisted = false,
        bool $isSponsored = false,
    ): array {
        if ($listing instanceof AdminListing) {
            return $this->toAdminCardShape($listing, $product, $country, $isWishlisted);
        }

        return $this->toCardShape($listing, $product, $country, $isWishlisted, $isSponsored);
    }

    public function wishlistListingIds(?string $customerId): array
    {
        if ($customerId === null) {
            return [];
        }

        $items = WishlistItem::where('customer_id', $customerId)->get(['vendor_listing_id', 'admin_listing_id']);

        return $items->pluck('vendor_listing_id')
            ->merge($items->pluck('admin_listing_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Paginate ClassifiedListing for a resolved category, including its direct children.
     */
    public function paginateForClassifiedCategory(
        string $categoryId,
        int $perPage = 20,
        array $filters = [],
    ): LengthAwarePaginator {
        $childIds = ClassifiedCategory::where('parent_id', $categoryId)->pluck('id');

        $query = ClassifiedListing::where('status', ClassifiedListingStatus::Active->value)
            ->where(function ($q) use ($categoryId, $childIds) {
                $q->where('classified_category_id', $categoryId)
                    ->orWhereIn('classified_category_id', $childIds);
            })
            ->with(['images', 'seller', 'city'])
            ->orderByDesc('created_at');

        if (!empty($filters['listing_purpose'])) {
            $query->where('listing_purpose', $filters['listing_purpose']);
        }

        if (!empty($filters['seller_type'])) {
            $query->where('seller_type', $filters['seller_type'] === 'vendor' ? Vendor::class : \App\Models\Customer::class);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', (int) $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', (int) $filters['max_price']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Canonical card shape for classified listings on browse/search grids.
     */
    public function toClassifiedCardShape(ClassifiedListing $listing): array
    {
        return [
            'listing_id' => $listing->id,
            'listing_number' => $listing->listing_number,
            'source_type' => 'classified',
            'title_en' => $listing->title_en,
            'title_ar' => $listing->title_ar,
            'slug' => $listing->listing_number,
            'thumbnail' => $listing->images->first()?->file_path
                ? \Illuminate\Support\Facades\Storage::url($listing->images->first()->file_path)
                : null,
            'images' => $listing->images->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => \Illuminate\Support\Facades\Storage::url($img->file_path),
                'is_primary' => (bool) ($img->is_primary ?? false),
                'position'   => (int) ($img->position ?? 0),
            ])->values()->all(),
            'price' => $listing->price,
            'price_formatted' => number_format($listing->price / 100, 2),
            'currency' => $listing->currency,
            'price_negotiable' => (bool) $listing->price_negotiable,
            'listing_purpose' => $listing->listing_purpose,
            'location' => $listing->city?->name_en,
            'seller_type' => $listing->seller_type === Vendor::class ? 'vendor' : 'customer',
            'images_count' => $listing->images->count(),
            'created_at' => $listing->created_at?->toIso8601String(),
        ];
    }

    /**
     * Paginate active TravelPackage, optionally filtered by travel category.
     */
    public function paginateTravelPackages(?string $travelCategoryId, int $perPage = 20): LengthAwarePaginator
    {
        return TravelPackage::where('status', TravelPackageStatus::Active->value)
            ->when($travelCategoryId, fn($q) => $q->whereHas(
                'categories',
                fn($q2) => $q2->where('travel_categories.id', $travelCategoryId),
            ))
            ->with([
                'agency:id,name',
                'categories:id,name_en,name_ar,slug',
                'media' => fn($q) => $q->orderBy('position')->limit(1),
            ])
            ->orderByDesc('departure_date')
            ->paginate($perPage);
    }

    /**
     * Canonical card shape for travel packages on browse grids.
     */
    public function toTravelCardShape(TravelPackage $package): array
    {
        return [
            'package_id' => $package->id,
            'source_type' => 'travel',
            'title_en' => $package->title_en,
            'title_ar' => $package->title_ar,
            'slug' => $package->id,
            'thumbnail' => $package->media->first()?->url(),
            'images' => $package->media->map(fn ($m) => [
                'id'       => $m->id,
                'url'      => $m->url(),
                'position' => (int) ($m->order_column ?? $m->position ?? 0),
            ])->values()->all(),
            'destination_country' => $package->destination_country,
            'destination_city' => $package->destination_city,
            'departure_date' => $package->departure_date?->toDateString(),
            'return_date' => $package->return_date?->toDateString(),
            'duration_days' => $package->duration_days,
            'duration_nights' => $package->duration_nights,
            'price' => $package->price,
            'price_formatted' => number_format($package->price / 100, 2),
            'currency' => $package->currency,
            'available_seats' => $package->available_seats,
            'seats_remaining' => $package->seatsRemaining(),
            'agency_name' => $package->agency?->name,
            'categories' => $package->categories->map(fn($c) => [
                'name_en' => $c->name_en,
                'slug' => $c->slug,
            ])->toArray(),
            'link' => '/travel',
        ];
    }
}
