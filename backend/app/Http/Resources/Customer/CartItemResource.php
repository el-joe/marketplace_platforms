<?php

namespace App\Http\Resources\Customer;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Customer\ListingIdentifierService;
use App\Services\Customer\ProductDetailEnrichmentService;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isMarketer = !is_null($this->marketer_listing_id);
        $isAdmin = !$isMarketer && !is_null($this->admin_listing_id);
        $listing = $isMarketer ? $this->marketerListing : ($isAdmin ? $this->adminListing : $this->vendorListing);
        $variant = $listing?->productVariant;
        $product = $variant?->product;
        $available = (!$isMarketer && $listing) ? $listing->warehouseInventories->sum('quantity_available') : null;

        $applicableCoupons = [];
        $country = $request->attributes->get('country');
        if ($product && $country) {
            $customer = auth('customer')->user();
            /** @var ProductDetailEnrichmentService $enrichment */
            $enrichment = app(ProductDetailEnrichmentService::class);
            $applicableCoupons = $enrichment->getApplicableCoupons(
                $product,
                $country,
                $customer,
                (!$isAdmin && !$isMarketer && $listing) ? $listing : null,
            );
        }

        return [
            'cart_item_id'       => $this->id,
            'listing_id'         => $listing?->id,
            'listing_ref'        => ($listing && !$isMarketer)
                ? app(ListingIdentifierService::class)->buildListingRef($listing)
                : ($listing?->referral_code ?? $listing?->id),
            'sku'                => $variant?->sku,
            'vendor_sku'         => ($isAdmin || $isMarketer) ? null : $listing?->vendor_sku,
            'name'               => $product ? Bilingual::pair($product, 'name') : ['ar' => null, 'en' => null],
            'thumbnail'          => $this->resolveThumbnail($product, $variant),
            'unit_price'   => $this->unit_price,
            'quantity'           => $this->quantity,
            'line_total'   => $this->unit_price * $this->quantity,
            'max_order_quantity' => $listing?->max_order_quantity,
            'vendor'             => !$isAdmin && !$isMarketer && $listing?->vendor ? [
                'id'         => $listing->vendor->id,
                'store_name' => $listing->vendor->store_name,
            ] : null,
            'promo_badges' => $listing ? \App\Services\Customer\PromoBadgeResolver::instance()->lookup($isMarketer ? 'marketer' : ($isAdmin ? 'admin' : 'vendor'), $listing->id, $product?->id) : [],
            'is_admin_listing' => $isAdmin,
            'listing_type' => $isMarketer ? 'marketer' : ($isAdmin ? 'admin' : 'vendor'),
            'shipping_badge'   => (!$isMarketer && $listing?->primaryShippingMethod) ? [
                'label'             => Bilingual::pairFromKeys($listing->primaryShippingMethod, 'badge_label_ar', 'badge_label_en'),
                'color_hex'         => $listing->primaryShippingMethod->badge_color_hex,
                'text_color_hex'    => $listing->primaryShippingMethod->badge_text_color_hex,
                'show_delivery_time' => (bool) $listing->primaryShippingMethod->badge_show_delivery_time,
                'delivery_text' => ['ar' => $listing->primaryShippingMethod->badge_delivery_text_resolved_ar, 'en' => $listing->primaryShippingMethod->badge_delivery_text_resolved_en],
                'icon' => (($listing->primaryShippingMethod->badge_icon ?? 'bolt') === 'none') ? null : ($listing->primaryShippingMethod->badge_icon ?? 'bolt'),
                'badge_image_url' => $listing->primaryShippingMethod->badge_image_url,
                'delivery_days_min' => $listing->primaryShippingMethod->min_delivery_days,
                'delivery_days_max' => $listing->primaryShippingMethod->max_delivery_days,
            ] : null,
            'in_stock'      => $isMarketer ? (bool) $listing : ($listing ? $available >= $this->quantity : false),
            'price_changed' => (bool) ($this->price_changed ?? false),
            'selected_shipping_method' => $this->selectedShippingMethod ? [
                'id'              => $this->selectedShippingMethod->id,
                'name'            => $this->selectedShippingMethod->name,
                'code'            => $this->selectedShippingMethod->code,
                'badge_label_en'  => $this->selectedShippingMethod->badge_label_en,
                'badge_color_hex' => $this->selectedShippingMethod->badge_color_hex,
            ] : null,
            'applicable_coupons' => $applicableCoupons,
            'warranty_plan_id' => $this->warranty_plan_id,
            'warranty_plan' => $this->whenLoaded('warrantyPlan', fn () => $this->warrantyPlan ? [
                'id' => $this->warrantyPlan->id,
                'name' => $this->warrantyPlan->localized_name,
                'duration_months' => $this->warrantyPlan->duration_months,
                'price' => $this->warrantyPlan->resolvePrice((int) $this->unit_price),
                'price_type' => $this->warrantyPlan->price_type,
                'price_pct' => $this->warrantyPlan->price_pct,
                'currency' => $this->warrantyPlan->currency,
                'image_url' => $this->warrantyPlan->image_url,
            ] : null),
        ];
    }

    /**
     * Mirrors ProductDetailController::buildImages() scoping so the cart thumbnail
     * matches the primary image shown on the product detail page: the variant's own
     * first-position image, falling back to a generic (variant-less) product image.
     */
    private function resolveThumbnail(?Product $product, ?ProductVariant $variant): ?string
    {
        $variantImage = $variant?->images?->firstWhere('is_primary', true)
            ?? $variant?->images?->first();

        $genericImage = $product?->images?->whereNull('product_variant_id')->sortBy('position')->first();

        return ($variantImage ?? $genericImage)?->url;
    }
}
