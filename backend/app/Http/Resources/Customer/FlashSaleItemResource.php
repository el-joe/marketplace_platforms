<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a FlashSaleSubmission for the customer-facing flash_sale page block.
 * Mirrors ProductListResource's conventions (locale-aware name, resolved image
 * URL, prices already in cents from the underlying columns).
 *
 * SECURITY: never expose vendor-internal fields (admin_notes, vendor_notes,
 * rejection_reason/code, reviewed_by_admin_id) or anything from
 * marketer_secret_promotions (admin_share_pct, product_value).
 */
class FlashSaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->vendorListing;
        $variant = $listing?->productVariant;
        $product = $variant?->product;
        $images = $variant ? app(\App\Services\Media\ListingImageResolver::class)->gallery($variant->id) : [];
        $imageUrl = $images[0]->url ?? null;

        return [
            'id' => $this->id,
            'product' => [
                'id' => $product?->id,
                'name' => $product ? Bilingual::pair($product, 'name') : ['ar' => null, 'en' => null],
                'slug' => $product?->slug,
                'image' => $imageUrl,
            ],
            'flash_price' => (int) $this->flash_price,
            'original_price' => (int) $this->original_price,
            'currency' => $this->flash_price_currency,
            'discount_pct' => (float) $this->calculated_discount_pct,
            'max_quantity_per_customer' => (int) $this->max_quantity_per_customer,
            // quantity_remaining is a MySQL GENERATED VIRTUAL column — read as-is,
            // never recalculated here.
            'quantity_remaining' => (int) $this->quantity_remaining,
        ];
    }
}
