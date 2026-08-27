<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminListingResource extends JsonResource
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

        $variantImagesSlider = $variant->images
            ->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_primary' => (bool) $img->is_primary,
                'position'   => (int) $img->position,
                'variant_id' => $img->product_variant_id,
            ])->values()->all();

        $productImagesSlider = $product->images
            ->filter(fn ($img) => $img->product_variant_id === null)
            ->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_primary' => (bool) $img->is_primary,
                'position'   => (int) $img->position,
                'variant_id' => null,
            ])->values()->all();

        $imagesSlider = array_values(array_merge($variantImagesSlider, $productImagesSlider));

        $url = route('customer.listing.show', [$this->country->site_code, $variant->id .'--' . $listing->id]);
        $url_param = $variant->id .'--' . $listing->id;


        return [
            'listing_id' => $listing->id,
            'listing_type' => 'admin',
            'variant_id' => $variant->id,
            'variant_name' => $variant->variant_name ?? $variant->sku,
            'product_url' => $url, // ✓ correct UUID format
            'primary_image' => $primaryImage?->url,
            'images' => $imagesSlider,
            'price' => (int) $listing->getRawOriginal('price'),
            'compare_at_price' => null,
            'currency' => $listing->currency ?? $this->country->currency_code,
            'status' => $listing->status?->value,
            'rating_avg' => (float) $listing->rating_avg,
            'rating_count' => (int) $listing->rating_count,
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
                'id' => $listing->productVariant->id,
                'sku' => $listing->productVariant->sku,
                'name_ar' => $listing->productVariant->name_ar,
                'name_en' => $listing->productVariant->name_en,
            ],
            'url_param' => $url_param,
        ];
    }
}
