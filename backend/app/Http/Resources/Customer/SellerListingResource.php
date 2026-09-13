<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->warehouseInventories->sum('quantity_available');

        $country = $request->attributes->get('country');

        return [
            'id'                 => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'url'               => $country ? route('customer.listing.show', [$country->site_code, $this->product_variant_id . '--' . $this->id]) : null,
            'seller_name'       => $this->vendor?->store_name,
            'seller_slug'       => $this->vendor?->store_slug,
            'price'             => $this->price,
            'currency'          => $this->currency,
            'condition'         => $this->condition,
            'condition_notes'   => $this->condition_notes,
            'fulfillment_model' => $this->fulfillment_model,
            'delivery_estimate' => $this->fulfillment_model === 'fbn' ? '1-2 days' : '3-7 days',
            'delivery_badge'    => $this->whenLoaded('primaryShippingMethod', fn() => $this->primaryShippingMethod ? [
                'label'           => [
                    'ar' => $this->primaryShippingMethod->badge_label_ar,
                    'en' => $this->primaryShippingMethod->badge_label_en,
                ],
                'color_hex'       => $this->primaryShippingMethod->badge_color_hex,
                'text_color_hex'  => $this->primaryShippingMethod->badge_text_color_hex,
                'min_delivery_days' => $this->primaryShippingMethod->min_delivery_days,
                'max_delivery_days' => $this->primaryShippingMethod->max_delivery_days,
            ] : null),
            'is_in_stock'       => $stock > 0,
            'stock_level'       => $stock > 10 ? 'high' : ($stock > 0 ? 'low' : 'out_of_stock'),
            'max_order_quantity' => $this->max_order_quantity,
            'is_buy_box_winner' => $this->score !== null && $this->score > 0,
            'vendor_details'    => $this->vendor ? [
                'rating_avg'              => (float) $this->vendor->store_rating_avg,
                'rating_count'            => (int) $this->vendor->store_rating_count,
                'positive_rating_pct'     => $this->vendor->positive_rating_pct,
                'item_as_shown_pct'       => $this->vendor->positive_rating_pct,
                'partner_since_years'     => $this->vendor->partner_years,
                'warranty_months'         => $this->vendor->warranty_months,
                'easy_returns_enabled'    => (bool) $this->vendor->easy_returns_enabled,
                'secure_payments_enabled' => (bool) $this->vendor->secure_payments_enabled,
            ] : null,
            // NOTE: cost_price and vendor_notes are NEVER exposed here
        ];
    }
}
