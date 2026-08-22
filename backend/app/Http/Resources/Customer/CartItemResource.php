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
        $isAdmin = !is_null($this->admin_listing_id);
        $listing = $isAdmin ? $this->adminListing : $this->vendorListing;
        $variant = $listing?->productVariant;
        $product = $variant?->product;
        $available = $listing?->warehouseInventories->sum('quantity_available') ?? 0;

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
                (!$isAdmin && $listing) ? $listing : null,
            );
        }

        return [
            'cart_item_id'       => $this->id,
            'listing_id'         => $listing?->id,
            'listing_ref'        => $listing ? app(ListingIdentifierService::class)->buildListingRef($listing) : null,
            'sku'                => $variant?->sku,
            'vendor_sku'         => $isAdmin ? null : $listing?->vendor_sku,
            'name'               => $product ? Bilingual::pair($product, 'name') : ['ar' => null, 'en' => null],
            'thumbnail'          => $this->resolveThumbnail($product, $variant),
            'unit_price'   => $this->unit_price,
            'quantity'           => $this->quantity,
            'line_total'   => $this->unit_price * $this->quantity,
            'max_order_quantity' => $listing?->max_order_quantity,
            'vendor'             => !$isAdmin && $listing?->vendor ? [
                'id'         => $listing->vendor->id,
                'store_name' => $listing->vendor->store_name,
            ] : null,
            'is_admin_listing' => $isAdmin,
            'shipping_badge'   => $listing?->primaryShippingMethod ? [
                'label'             => Bilingual::pairFromKeys($listing->primaryShippingMethod, 'badge_label_ar', 'badge_label_en'),
                'color_hex'         => $listing->primaryShippingMethod->badge_color_hex,
                'text_color_hex'    => $listing->primaryShippingMethod->badge_text_color_hex,
                'delivery_days_min' => $listing->primaryShippingMethod->min_delivery_days,
                'delivery_days_max' => $listing->primaryShippingMethod->max_delivery_days,
            ] : null,
            'in_stock'      => $listing ? $available >= $this->quantity : false,
            'price_changed' => (bool) ($this->price_changed ?? false),
            'selected_shipping_method' => $this->selectedShippingMethod ? [
                'id'              => $this->selectedShippingMethod->id,
                'name'            => $this->selectedShippingMethod->name,
                'code'            => $this->selectedShippingMethod->code,
                'badge_label_en'  => $this->selectedShippingMethod->badge_label_en,
                'badge_color_hex' => $this->selectedShippingMethod->badge_color_hex,
            ] : null,
            'applicable_coupons' => $applicableCoupons,
        ];
    }

    /**
     * Mirrors ProductDetailController::buildImages() scoping so the cart thumbnail
     * matches the primary image shown on the product detail page: the variant's own
     * first-position image, falling back to a generic (variant-less) product image.
     */
    private function resolveThumbnail(?Product $product, ?ProductVariant $variant): ?string
    {
        $images = $product?->images;

        if (!$images) {
            return null;
        }

        $variantImage = $variant
            ? $images->where('product_variant_id', $variant->id)->sortBy('position')->first()
            : null;

        $genericImage = $images->whereNull('product_variant_id')->sortBy('position')->first();

        return ($variantImage ?? $genericImage)?->url;
    }
}
