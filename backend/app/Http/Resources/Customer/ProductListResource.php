<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = [
            'ar' => $this->name_override_ar ?? $this->name_ar,
            'en' => $this->name_override_en ?? $this->name_en,
        ];

        $variantImage = $this->buy_box_variant_image_path
            ? \Storage::disk($this->buy_box_variant_image_disk)->url($this->buy_box_variant_image_path)
            : null;

        $imagesSlider = $this->whenLoaded('images', function () {
            return $this->images
                ->map(fn ($img) => [
                    'id'         => $img->id,
                    'url'        => \Storage::disk($img->disk ?? 'public')->url($img->path),
                    'alt'        => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                    'is_primary' => (bool) $img->is_primary,
                    'position'   => (int) $img->position,
                    'variant_id' => $img->product_variant_id,
                ])->values()->all();
        }, []);

        // listing_type is now driven by the actual buy-box winner ('admin' or 'vendor')
        $listingType = $this->buy_box_listing_type ?? 'vendor';

        // product_url — admin listings use the plain listing UUID (resolved by ListingDetailController);
        // vendor listings keep the variant--listing format.
        $productUrl = null;
        if ($this->buy_box_listing_id) {
            if ($listingType === 'admin') {
                $productUrl = "/products/{$this->buy_box_listing_id}";
            } elseif ($this->buy_box_variant_id) {
                $productUrl = "/products/{$this->buy_box_variant_id}/{$this->buy_box_listing_id}";
            }
        }

        return [
            'id'                  => $this->id,
            'listing_id'          => $this->buy_box_listing_id,
            'listing_type'        => $listingType,
            'variant_id'          => $this->buy_box_variant_id,
            'product_slug'        => $this->slug,
            'slug'                => $this->slug,
            'variant_slug'        => $this->buy_box_variant_slug,
            'variant_name'        => $this->buy_box_variant_name ?? null,
            'variant_image'       => $variantImage,
            'product_url'         => $productUrl,
            'name'                => $name,
            'primary_image'       => $variantImage ?? $this->whenLoaded('images', function () {
                $primary = $this->images->firstWhere('is_primary', true) ?? $this->images->first();
                return $primary ? \Storage::disk($primary->disk)->url($primary->path) : null;
            }),
            'images'              => $imagesSlider,
            'price_range'         => [
                'min' => $this->min_price !== null ? round($this->min_price / 100, 2) : null,
                'max' => $this->max_price !== null ? round($this->max_price / 100, 2) : null,
            ],
            'category_name'       => [
                'en' => $this->category_name_en,
                'ar' => $this->category_name_ar,
            ],
            'compare_at_price'    => $this->buy_box_compare_at_price !== null ? (int) $this->buy_box_compare_at_price : null,
            'rating_avg'          => (float) $this->rating_avg,
            'rating_count'        => (int) $this->rating_count,
            'seller_count'        => (int) ($this->active_seller_count ?? $this->seller_count ?? 0),
            'admin_listing_count' => (int) ($this->admin_listing_count ?? 0),
            'total_seller_count'  => (int) ($this->active_seller_count ?? 0) + (int) ($this->admin_listing_count ?? 0),
            'is_in_stock'         => (int) ($this->total_stock ?? 0) > 0,
            'is_sponsored'        => (bool) ($this->is_sponsored ?? false),
            'is_wishlisted'       => (bool) ($this->is_wishlisted ?? false),
            'shipping_badge' => ($this->buy_box_shipping_label_en || $this->buy_box_shipping_label_ar)
                ? [
                    'label'            => [
                        'ar' => $this->buy_box_shipping_label_ar,
                        'en' => $this->buy_box_shipping_label_en,
                    ],
                    'color_hex'        => $this->buy_box_shipping_color_hex,
                    'text_color_hex'   => $this->buy_box_shipping_text_color_hex,
                    'badge_image_url'  => $this->buy_box_shipping_badge_image_path
                        ? \Storage::disk('public')->url($this->buy_box_shipping_badge_image_path)
                        : null,
                    'delivery_days_min' => $this->buy_box_shipping_days_min !== null ? (int) $this->buy_box_shipping_days_min : null,
                    'delivery_days_max' => $this->buy_box_shipping_days_max !== null ? (int) $this->buy_box_shipping_days_max : null,
                    'is_express'       => (bool) $this->buy_box_shipping_is_express,
                ]
                : null,
        ];
    }
}
