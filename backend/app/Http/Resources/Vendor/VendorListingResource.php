<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->relationLoaded('productVariant') ? $this->productVariant : null;
        $product = $variant?->relationLoaded('product') === true ? $variant->product : null;
        $thumbnail = $product?->relationLoaded('images') === true
            ? ($product->images->firstWhere('is_primary', true)?->url ?? $product->images->first()?->url)
            : null;

        return [
            'id'                   => $this->id,
            'vendor_sku'           => $this->vendor_sku,
            'status'               => $this->status?->value,
            'condition'            => $this->condition,
            'fulfillment_model'    => $this->fulfillment_model,
            'global_system_type'   => $this->global_system_type?->value,
            'vendor_covers_delivery' => (bool) $this->vendor_covers_delivery,
            'vendor_covers_delivery_editable' => $this->global_system_type?->value === 'merchant_fbp',
            'price'                => (int) $this->price,
            'compare_at_price'     => $this->compare_at_price ? (int) $this->compare_at_price : null,
            'cost_price'           => $this->cost_price !== null ? (int) $this->cost_price : null,
            'currency'             => $this->currency,
            'total_sold'           => (int) $this->total_sold,
            'rating_avg'           => (float) $this->rating_avg,
            'rejection_reason'     => $this->rejection_reason,

            'sku'                 => $variant?->sku,
            'variant_name'        => $variant?->variant_name,
            'product_name_en'     => $product?->name_en,
            'product_name_ar'     => $product?->name_ar,
            'thumbnail'           => $thumbnail,
            'variant_attributes'  => $variant?->relationLoaded('variantAttributes') === true
                ? $variant->variantAttributes->map(fn ($attr) => [
                    'attribute_id' => $attr->attribute_id,
                    'value_en'     => $attr->value_text_en,
                    'value_ar'     => $attr->value_text_ar,
                ])->values()
                : [],

            'product_variant'      => $this->whenLoaded('productVariant', fn () => [
                'id'           => $this->productVariant->id,
                'sku'          => $this->productVariant->sku,
                'variant_name' => $this->productVariant->variant_name,
                'product'      => $this->productVariant->relationLoaded('product') ? [
                    'id'      => $this->productVariant->product->id,
                    'name_en' => $this->productVariant->product->name_en,
                    'name_ar' => $this->productVariant->product->name_ar,
                ] : null,
            ]),
            'country'              => $this->whenLoaded('country', fn () => [
                'id'   => $this->country->id,
                'name' => $this->country->name,
            ]),
            'primary_shipping_method' => $this->whenLoaded('primaryShippingMethod', fn () => $this->primaryShippingMethod ? [
                'id'                   => $this->primaryShippingMethod->id,
                'name'                 => $this->primaryShippingMethod->name,
                'badge_label_en'       => $this->primaryShippingMethod->badge_label_en,
                'badge_label_ar'       => $this->primaryShippingMethod->badge_label_ar,
                'badge_color_hex'      => $this->primaryShippingMethod->badge_color_hex,
                'badge_text_color_hex' => $this->primaryShippingMethod->badge_text_color_hex,
                'is_express_type'      => (bool) $this->primaryShippingMethod->is_express_type,
            ] : null),
            'created_at'           => $this->created_at->toIso8601String(),
            'updated_at'           => $this->updated_at->toIso8601String(),
        ];
    }
}
