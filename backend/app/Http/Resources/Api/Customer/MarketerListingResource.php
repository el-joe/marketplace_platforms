<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketerListingResource extends JsonResource
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

        if (!$variant || !$product) {
            return [
                'listing_id' => $listing->id,
                'listing_type' => 'marketer',
                'variant_id' => null,
                'primary_image' => null,
                'images' => [],
                'product' => null,
            ];
        }

        $images = app(\App\Services\Media\ListingImageResolver::class)->gallery($variant->id);
        $imagesSlider = array_map(fn ($img) => $img->toArray(), $images);
        $primaryImageUrl = $images[0]->url ?? null;

        $url_param = $variant->id . '--' . $listing->id;
        $url = route('customer.listing.show', [$this->country->site_code, $url_param]);

        $marketer = $listing->marketer;
        $profile = $marketer?->marketerProfile;

        return [
            'listing_id' => $listing->id,
            'listing_type' => 'marketer',
            'variant_id' => $variant->id,
            'variant_name' => $variant->setRelation('product', $product)->displayName(),
            'product_url' => $url,
            'url_param' => $url_param,
            'primary_image' => $primaryImageUrl,
            'image' => $primaryImageUrl ? ['url' => $primaryImageUrl, 'alt' => $images[0]->alt] : null,
            'images' => $imagesSlider,
            'price' => (int) $listing->price,
            'compare_at_price' => $listing->compare_at_price !== null ? (int) $listing->compare_at_price : null,
            'currency' => $listing->currency ?? $this->country->currency_code,
            'condition' => $listing->condition,
            'status' => $listing->status,
            'rating_avg' => (float) $listing->rating_avg,
            'rating_count' => (int) $listing->rating_count,
            'total_sold' => (int) $listing->total_sold,
            'shipping_badge' => null,
            'referral_code' => $listing->referral_code,
            'marketer' => $marketer ? [
                'id' => $marketer->id,
                'name' => $marketer->name,
                'profile_slug' => $profile?->profile_slug,
                'profile_url' => $profile?->profile_slug ? "/marketers/{$profile->profile_slug}" : null,
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
                'images' => $product->images->map(fn ($image) => [
                    'id'         => $image->id,
                    'url'        => $image->url,
                    'alt'        => ['ar' => $image->alt_text_ar, 'en' => $image->alt_text_en],
                    'is_primary' => (bool) $image->is_primary,
                    'position'   => (int) $image->position,
                    'variant_id' => $image->product_variant_id,
                ])->values()->all(),
            ],
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name_ar' => $variant->name_ar,
                'name_en' => $variant->name_en,
            ],
        ];
    }
}
