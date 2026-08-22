<?php

namespace App\Http\Resources\Customer;

use App\Models\OrderItem;
use App\Services\Customer\ListingIdentifierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sub-order summary block returned in the placeOrder() success response.
 * Wraps a SubOrder model with items.vendorListing loaded.
 */
class PlacedSubOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sub_order_number' => $this->sub_order_number,
            'vendor' => $this->vendor?->store_name,
            'status' => $this->status->value,
            'fulfillment_model' => $this->fulfillment_model,
            'delivery_fee' => $this->shipping,
            'is_free_delivery' => $this->shipping === 0,
            'items' => $this->items->map(fn (OrderItem $item) => [
                'listing_ref' => $item->vendorListing
                    ? app(ListingIdentifierService::class)->buildListingRef($item->vendorListing)
                    : null,
                'sku' => $item->sku,
                'name_en' => $item->product_snapshot['name_en'] ?? null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]),
        ];
    }
}
