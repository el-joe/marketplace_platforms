<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'warehouse_id'       => $this->warehouse_id,
            'vendor_listing_id'  => $this->vendor_listing_id,
            'quantity_on_hand'   => (int) $this->quantity_on_hand,
            'quantity_reserved'  => (int) $this->quantity_reserved,
            // Read the VIRTUAL column directly — never recalculate in PHP
            'quantity_available' => (int) $this->quantity_available,
            'quantity_inbound'   => (int) $this->quantity_inbound,
            'quantity_damaged'   => (int) $this->quantity_damaged,
            'bin_location'       => $this->bin_location,
            'reorder_point'      => $this->reorder_point,
            'last_counted_at'    => $this->last_counted_at?->toIso8601String(),
            'warehouse'          => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'type' => $this->warehouse->type?->value,
            ]),
            'listing'            => $this->whenLoaded('vendorListing', fn () => [
                'id'           => $this->vendorListing->id,
                'condition'    => $this->vendorListing->condition,
                'price'        => (int) $this->vendorListing->price,
                'product_variant' => $this->vendorListing->relationLoaded('productVariant') ? [
                    'sku'          => $this->vendorListing->productVariant->sku,
                    'variant_name' => $this->vendorListing->productVariant->variant_name,
                ] : null,
            ]),
            'updated_at'         => $this->updated_at->toIso8601String(),
        ];
    }
}
