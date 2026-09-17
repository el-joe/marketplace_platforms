<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorListingResource extends JsonResource
{
    public function __construct($resource, private readonly Country $country)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $listing = $this->resource;
        $variant = $listing->productVariant;
        $product = $variant?->product;

        // Listings whose variant/product was soft-deleted are filtered out
        // by the query (whereHas('productVariant.product')); this null-check
        // is defense in depth so a stray call site never 500s again
        // (enhancement.md P-17 task 7).
        if (!$variant || !$product) {
            return [
                'listing_id' => $listing->id,
                'listing_type' => 'vendor',
                'variant_id' => null,
                'primary_image' => null,
                'images' => [],
                'product' => null,
            ];
        }

        $images = app(\App\Services\Media\ListingImageResolver::class)->gallery($variant->id);
        $imagesSlider = array_map(fn ($img) => $img->toArray(), $images);
        $primaryImageUrl = $images[0]->url ?? null;

        $url = route('customer.listing.show', [$this->country->site_code, $variant->id . '--' . $listing->id]);
        $url_param = $variant->id . '--' . $listing->id;

        return [
            'listing_id' => $listing->id,
            'listing_type' => 'vendor',
            'variant_id' => $variant->id,
            'variant_name' => $variant->setRelation('product', $product)->displayName(),
            'product_url' => $url, // ✓ correct UUID format
            'url_param' => $url_param,
            'primary_image' => $primaryImageUrl,
            'image' => $primaryImageUrl ? ['url' => $primaryImageUrl, 'alt' => $images[0]->alt] : null,
            'images' => $imagesSlider,
            'price' => (int) $listing->price,
            'compare_at_price' => $listing->compare_at_price !== null ? (int) $listing->compare_at_price : null,
            'currency' => $listing->currency ?? $this->country->currency_code,
            'condition' => $listing->condition,
            'global_system_type' => $listing->global_system_type?->value,
            'status' => $listing->status?->value,
            'rating_avg' => (float) $listing->rating_avg,
            'rating_count' => (int) $listing->rating_count,
            'total_sold' => (int) $listing->total_sold,
            'vendor_covers_delivery' => (bool) $listing->vendor_covers_delivery,
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
            'brand' => $product->brand ? [
                'id'       => $product->brand->id,
                'name'     => ['ar' => $product->brand->name_ar, 'en' => $product->brand->name_en],
                'slug'     => $product->brand->slug,
                'logo_url' => $product->brand->logo_url,
            ] : null,
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name_ar' => $product->category->name_ar,
                    'name_en' => $product->category->name_en,
                ] : null,
                'images' => $product->images->map(fn($image) => [
                    'id'         => $image->id,
                    'url'        => $image->url,
                    'alt'        => ['ar' => $image->alt_text_ar, 'en' => $image->alt_text_en],
                    'is_primary' => (bool) $image->is_primary,
                    'position'   => (int) $image->position,
                    'variant_id' => $image->product_variant_id,
                ])->values()->all(),
            ],
            'variant' => [
                'id' => $listing->productVariant->id,
                'sku' => $listing->productVariant->sku,
                'name_ar' => $listing->productVariant->name_ar,
                'name_en' => $listing->productVariant->name_en,
            ],
        ];
    }
}
