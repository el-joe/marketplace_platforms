<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AdminListing;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VendorListing;
use App\Services\AppContextService;
use App\Services\ShippingMethodResolverService;
use App\Services\VariantResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDetailController extends Controller
{
    public function __construct(
        private readonly VariantResolutionService $variantResolutionService,
        private readonly AppContextService $appContext,
        private readonly ShippingMethodResolverService $shippingMethodResolver,
    ) {
    }

    public function show(string $variantId, string $listingId, Request $request): JsonResponse
    {
        $variant = ProductVariant::with(['product.brand', 'product.category'])
            ->where('id', $variantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$variant) {
            return ApiResponse::error(__('customer_api.product_detail.not_found'), [], 404);
        }

        $product = $variant->product;

        if (!$product || $product->status !== ProductStatus::Active) {
            return ApiResponse::error(__('customer_api.product_detail.not_found'), [], 404);
        }

        $countryId = $this->resolveCountryId($request);

        if (!$countryId) {
            return ApiResponse::error(__('customer_api.product_detail.country_not_found'), [], 404);
        }

        $isNawyNow = $this->appContext->isNawyNow();

        [$listing, $listingType] = $this->resolveListing($variantId, $listingId, $countryId, $isNawyNow);

        if (!$listing) {
            return ApiResponse::error(__('customer_api.product_detail.listing_not_found'), [], 404);
        }

        $url = route('customer.listing.show', [$request->attributes->get('country')->site_code, $variant->id .'--' . $listing->id]);
        $url_param = $variant->id .'--' . $listing->id;

        $shippingListingType = $listingType === 'vendor' ? 'vendor_listing' : 'admin_listing';

        $shippingMethods = $this->shippingMethodResolver->getAvailableForListing($listing->id, $shippingListingType, $countryId);

        $shippingMethodsData = $shippingMethods->map(fn ($method) =>
            $this->shippingMethodResolver->buildMethodResponse($method, $countryId, $method->is_default)
        )->values()->all();

        $selectedShippingMethodId = $this->resolveSelectedShippingMethodId($request, $shippingMethods);

        $unifiedListings = $this->buildOtherSellers($variantId, $countryId, $listing);

        $otherSellers = $isNawyNow
            ? []
            : array_values(array_filter($unifiedListings, fn (array $l) => !$l['is_current_listing']));

        return ApiResponse::success([
            'product' => $this->productShape($product),
            'variant' => $this->variantShape($variant),
            'listing' => $this->listingShape($listing, $listingType),
            'images' => $this->buildImages($product, $variant),
            'attributes' => $this->buildAttributeMatrix($product, $variant, $countryId, $isNawyNow),
            'other_sellers' => $otherSellers,
            'listings' => $unifiedListings,
            'buy_box' => $unifiedListings[0] ?? null,
            'shipping_methods' => $shippingMethodsData,
            'selected_shipping_method_id' => $selectedShippingMethodId,
            'current_url' => $url,
            'url_param' => $url_param
        ]);
    }

    private function resolveSelectedShippingMethodId(Request $request, \Illuminate\Support\Collection $shippingMethods): ?string
    {
        $requestedId = $request->query('shipping_method_id');

        if ($requestedId && $shippingMethods->contains('id', $requestedId)) {
            return $requestedId;
        }

        return $shippingMethods->firstWhere('is_default', true)?->id
            ?? $shippingMethods->first()?->id;
    }

    /**
     * Backward-compat redirect for old URLs that identified a product by slug only.
     */
    public function redirectBySlug(string $productSlug, Request $request): mixed
    {
        $product = Product::where('slug', $productSlug)
            ->where('status', ProductStatus::Active)
            ->first();

        if (!$product) {
            return ApiResponse::error(__('customer_api.product_detail.not_found'), [], 404);
        }

        $variant = $this->resolveDefaultVariant($product->id);

        if (!$variant) {
            return ApiResponse::error(__('customer_api.product_detail.not_found'), [], 404);
        }

        return $this->redirectToVariantListing($variant, $request);
    }

    /**
     * Backward-compat redirect for old URLs that identified a product + variant by slug.
     */
    public function redirectBySlugAndVariant(string $productSlug, string $variantSlug, Request $request): mixed
    {
        $product = Product::where('slug', $productSlug)
            ->where('status', ProductStatus::Active)
            ->first();

        if (!$product) {
            return ApiResponse::error(__('customer_api.product_detail.not_found'), [], 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('variant_name', $variantSlug)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$variant) {
            $variant = $this->resolveDefaultVariant($product->id);
        }

        if (!$variant) {
            return ApiResponse::error(__('customer_api.product_detail.not_found'), [], 404);
        }

        return $this->redirectToVariantListing($variant, $request);
    }

    private function resolveDefaultVariant(string $productId): ?ProductVariant
    {
        $variant = ProductVariant::where('product_id', $productId)
            ->where('is_default', 1)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if ($variant) {
            return $variant;
        }

        return ProductVariant::where('product_id', $productId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->first();
    }

    private function redirectToVariantListing(ProductVariant $variant, Request $request): mixed
    {
        $countryId = $this->resolveCountryId($request);

        if (!$countryId) {
            return ApiResponse::error(__('customer_api.product_detail.country_not_found'), [], 404);
        }

        $isNawyNow = $this->appContext->isNawyNow();

        $listing = $isNawyNow
            ? $this->variantResolutionService->bestAdminListing($variant->id, $countryId)
            : $this->variantResolutionService->bestVendorListing($variant->id, $countryId);

        if (!$listing) {
            return ApiResponse::error(__('customer_api.product_detail.listing_not_found'), [], 404);
        }

        $url = route('customer.listing.show', [$request->attributes->get('country')->site_code, $variant->id .'--' . $listing->id]);
        $url_param = $variant->id .'--' . $listing->id;


        if ($request->wantsJson() || $request->header('X-Requested-From') === 'mobile-app') {
            return ApiResponse::success(['redirect_url' => $url, 'url_param' => $url_param]);
        }

        return redirect($url, 301);
    }

    /**
     * @return array{0: VendorListing|AdminListing|null, 1: string}
     */
    private function resolveListing(string $variantId, string $listingId, string $countryId, bool $isNawyNow): array
    {
        if ($isNawyNow) {
            $listing = AdminListing::where('id', $listingId)
                ->where('product_variant_id', $variantId)
                ->where('status', AdminListingStatus::Active)
                ->first();

            if (!$listing) {
                $listing = $this->variantResolutionService->bestAdminListing($variantId, $countryId);
            }

            return [$listing, 'admin'];
        }

        $listing = VendorListing::where('id', $listingId)
            ->where('product_variant_id', $variantId)
            ->whereIn('status', [VendorListingStatus::Active, VendorListingStatus::OutOfStock])
            ->first();

        if (!$listing) {
            $listing = $this->variantResolutionService->bestVendorListing($variantId, $countryId);
        }

        return [$listing, 'vendor'];
    }

    private function resolveCountryId(Request $request): ?string
    {
        $customer = auth('customer')->user();

        if ($customer) {
            return $customer->country_id;
        }

        return $request->header('X-Country-Id');
    }

    private function productShape(Product $product): array
    {
        return [
            'id' => $product->id,
            'name_en' => $product->name_en,
            'name_ar' => $product->name_ar,
            'slug' => $product->slug,
            'description_en' => $product->description_en,
            'description_ar' => $product->description_ar,
            'short_desc_en' => $product->short_desc_en,
            'short_desc_ar' => $product->short_desc_ar,
            'brand' => $product->brand ? [
                'id'       => $product->brand->id,
                'name_en'  => $product->brand->name_en,
                'name_ar'  => $product->brand->name_ar,
                'logo_url' => $product->brand->logo_url,
            ] : null,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name_en' => $product->category->name_en,
                'name_ar' => $product->category->name_ar,
                'slug' => $product->category->slug,
            ] : null,
        ];
    }

    private function variantShape(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'variant_name' => $variant->variant_name,
            'weight_grams' => $variant->weight_grams,
            'length_cm' => $variant->length_cm,
            'width_cm' => $variant->width_cm,
            'height_cm' => $variant->height_cm,
        ];
    }

    private function listingShape(VendorListing|AdminListing $listing, string $listingType): array
    {
        $shape = [
            'id' => $listing->id,
            'listing_type' => $listingType,
            'price' => $listing->price,
            'compare_at_price' => $listing->compare_at_price,
            'currency' => $listing->currency,
            'shipping_cost' => $listingType === 'admin' ? $listing->shipping_cost : null,
            'fulfillment_type' => $listingType === 'admin' ? $listing->fulfillment_type : $listing->fulfillment_model,
            'payment_options' => $listingType === 'admin' ? $listing->payment_options : null,
            'status' => $listing->status instanceof \BackedEnum ? $listing->status->value : $listing->status,
            'rating_avg' => $listing->rating_avg,
            'rating_count' => $listing->rating_count,
        ];

        if ($listingType === 'vendor') {
            $listing->loadMissing('vendor');

            $shape['vendor'] = $listing->vendor ? [
                'id' => $listing->vendor->id,
                'name_en' => $listing->vendor->store_name,
                'name_ar' => $listing->vendor->store_name,
                'slug' => $listing->vendor->store_slug,
                'rating_avg' => $listing->vendor->store_rating_avg,
            ] : null;
        }

        return $shape;
    }

    /**
     * Builds the swatch/attribute matrix. For every variant-type attribute of the
     * product's category, collects the union of values across all active variants
     * of the product, and resolves what variant/listing each value would lead to.
     */
    private function buildAttributeMatrix(Product $product, ProductVariant $variant, string $countryId, bool $isNawyNow): array
    {
        $attributes = Attribute::query()
            ->join('category_attributes', 'category_attributes.attribute_id', '=', 'attributes.id')
            ->where('category_attributes.category_id', $product->category_id)
            ->where('attributes.is_variant_attribute', true)
            ->orderBy('attributes.sort_order')
            ->orderBy('category_attributes.sort_order')
            ->select('attributes.*')
            ->get();

        if ($attributes->isEmpty()) {
            return [];
        }

        $currentMap = $this->variantResolutionService->getVariantAttributeMap($variant->id);

        $allVariants = ProductVariant::where('product_id', $product->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['variantAttributes.attribute', 'variantAttributes.attributeValue'])
            ->get();

        $matrix = [];

        foreach ($attributes as $attribute) {
            $valuesById = [];

            foreach ($allVariants as $otherVariant) {
                foreach ($otherVariant->variantAttributes as $variantAttribute) {
                    if ($variantAttribute->attribute_id === $attribute->id && $variantAttribute->attributeValue) {
                        $valuesById[$variantAttribute->attribute_value_id] = $variantAttribute->attributeValue;
                    }
                }
            }

            $selectedValueId = $currentMap[$attribute->id] ?? null;

            $values = collect($valuesById)->map(function ($attributeValue) use (
                $attribute,
                $selectedValueId,
                $product,
                $variant,
                $countryId,
                $isNawyNow,
            ) {
                $isSelected = $selectedValueId !== null && (string) $attributeValue->id === (string) $selectedValueId;

                $targetVariant = $this->variantResolutionService->resolveVariantAfterChange(
                    $product->id,
                    $variant->id,
                    $attribute->id,
                    $attributeValue->id,
                );

                $targetListing = null;

                if ($targetVariant) {
                    $targetListing = $isNawyNow
                        ? $this->variantResolutionService->bestAdminListing($targetVariant->id, $countryId)
                        : $this->variantResolutionService->bestVendorListing($targetVariant->id, $countryId);
                }

                $url = route('customer.listing.show', [request()->attributes->get('country')->site_code,$targetVariant->id .'--' . $targetListing->id]);
                $url_param = $targetVariant->id .'--' . $targetListing->id;

                return [
                    'attribute_value_id' => $attributeValue->id,
                    'value_en' => $attributeValue->value_en,
                    'value_ar' => $attributeValue->value_ar,
                    'code_hex' => $attribute->type === AttributeType::Color ? $attributeValue->color_hex : null,
                    'is_selected' => $isSelected,
                    'is_available' => $targetVariant !== null && $targetListing !== null,
                    'target_variant_id' => $targetVariant?->id,
                    'target_listing_id' => $targetListing?->id,
                    'url' => $url,
                    'url_param' => $url_param,
                    'target_url' => ($targetVariant && $targetListing)
                        ? $url
                        : null,
                ];
            })->values()->all();

            $matrix[] = [
                'attribute_id' => $attribute->id,
                'attribute_code' => $attribute->code,
                'attribute_name_en' => $attribute->name_en,
                'attribute_name_ar' => $attribute->name_ar,
                'type' => $attribute->type?->value,
                'selected_value_id' => $selectedValueId,
                'values' => $values,
            ];
        }

        return $matrix;
    }

    private function buildOtherSellers(string $variantId, string $countryId, VendorListing|AdminListing $currentListing): array
    {
        $adminListings = AdminListing::with(['warehouseInventories', 'primaryShippingMethod'])
            ->where('product_variant_id', $variantId)
            ->where('country_id', $countryId)
            ->where('status', AdminListingStatus::Active->value)
            ->orderByDesc('search_boost')
            ->orderBy('created_at')
            ->get();

        $vendorListings = VendorListing::with(['warehouseInventories', 'primaryShippingMethod', 'vendor'])
            ->where('product_variant_id', $variantId)
            ->where('country_id', $countryId)
            ->whereIn('status', [VendorListingStatus::Active, VendorListingStatus::OutOfStock])
            ->orderByDesc('score')
            ->get();

        $listings = $adminListings->map(fn (AdminListing $listing) => $this->formatListing($listing, 'admin', $currentListing))
            ->concat($vendorListings->map(fn (VendorListing $listing) => $this->formatListing($listing, 'vendor', $currentListing)));

        return $listings->values()->all();
    }

    private function formatListing(VendorListing|AdminListing $listing, string $source, VendorListing|AdminListing $currentListing): array
    {
        return [
            'id' => $listing->id,
            'price' => $listing->price,
            'compare_at_price' => $listing->compare_at_price,
            'currency' => $listing->currency,
            'condition' => $listing->condition,
            'condition_notes' => $listing->condition_notes,
            'fulfillment_model' => $source === 'admin' ? $listing->fulfillment_type : $listing->fulfillment_model,
            'global_system_type' => $source === 'vendor' ? $listing->global_system_type?->value : null,
            'is_global_shipping' => $source === 'admin' ? (bool) $listing->is_global_shipping : false,
            'vendor_covers_delivery' => (bool) $listing->vendor_covers_delivery,
            'max_order_quantity' => $listing->max_order_quantity,
            'status' => $listing->status instanceof \BackedEnum ? $listing->status->value : $listing->status,
            'rating_avg' => $listing->rating_avg,
            'rating_count' => $listing->rating_count,
            'stock' => $listing->warehouseInventories->sum('quantity_available'),
            'buy_box_won' => $listing->buy_box_won_at !== null,
            'is_current_listing' => (string) $listing->id === (string) $currentListing->id,
            // Seller identity
            'seller' => $source === 'admin' ? [
                'id' => null,
                'name_en' => $listing->sold_by_label_en,
                'name_ar' => $listing->sold_by_label_ar,
                'is_platform' => true,
            ] : [
                'id' => $listing->vendor_id,
                'name_en' => $listing->vendor?->store_name,
                'name_ar' => $listing->vendor?->store_name_ar ?? $listing->vendor?->store_name,
                'is_platform' => false,
            ],
            // Express badge — admin listings only
            'badge' => $source === 'admin' ? [
                'type' => 'express',
                'label_en' => $listing->express_badge_label_en,
                'label_ar' => $listing->express_badge_label_ar,
                'color' => '#FFCC00',
            ] : null,
            'is_daily_deal' => $source === 'admin' ? (bool) $listing->is_daily_deal : false,
            // NEVER expose: cost_price, score, price_score, fulfillment_score,
            //               campaign_enabled, created_by_admin_id, search_boost
        ];
    }

    private function buildImages(Product $product, ProductVariant $variant): array
    {
        $variantImages = ProductImage::where('product_variant_id', $variant->id)
            ->orderBy('position')
            ->get()
            ->map(fn (ProductImage $img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_primary' => (bool) $img->is_primary,
                'position'   => (int) $img->position,
                'variant_id' => $img->product_variant_id,
            ])->values()->all();

        $productImages = ProductImage::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->orderBy('position')
            ->get()
            ->map(fn (ProductImage $img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_primary' => (bool) $img->is_primary,
                'position'   => (int) $img->position,
                'variant_id' => null,
            ])->values()->all();

        return array_values(array_merge($variantImages, $productImages));
    }
}
