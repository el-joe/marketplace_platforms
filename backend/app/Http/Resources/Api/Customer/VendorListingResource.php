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
        $product = $variant->product;
        $primaryImage = $variant->images->firstWhere('is_primary', true)
            ?? $variant->images->first()
            ?? $product->images->firstWhere('is_primary', true)
            ?? $product->images->first();

        $url = route('customer.listing.show', [$this->country->site_code, $variant->id . '--' . $listing->id]);
        $url_param = $variant->id . '--' . $listing->id;

        return [
            'listing_id' => $listing->id,
            'listing_type' => 'vendor',
            'variant_id' => $variant->id,
            'variant_name' => $variant->variant_name ?? $variant->sku,
            'product_url' => $url, // ✓ correct UUID format
            'url_param' => $url_param,
            'primary_image' => $primaryImage?->url,
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
                    'url' => $image->url,
                    'is_primary' => (bool) $image->is_primary,
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
