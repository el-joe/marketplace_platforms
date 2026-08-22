<?php

namespace App\Http\Resources\Customer;

use App\Services\Customer\ListingIdentifierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status->value,
            'payment_status'   => $this->payment_status?->value,
            'payment_method'   => $this->payment_method,
            'currency'         => $this->currency,
            'subtotal'         => round($this->subtotal / 100, 2),
            'discount'         => round($this->discount / 100, 2),
            'shipping'         => round($this->shipping / 100, 2),
            'tax'              => round($this->tax / 100, 2),
            'cod_fee'          => round($this->cod_fee / 100, 2),
            'total'            => round($this->total / 100, 2),
            'placed_at'        => $this->placed_at?->toIso8601String(),
            'shipping_address' => $this->shipping_address_snapshot,
            'coupon_code_used' => $this->coupon_code_used,
            'sub_orders'       => $this->whenLoaded('subOrders', fn () =>
                $this->subOrders->map(fn ($so) => [
                    'id'                      => $so->id,
                    'sub_order_number'        => $so->sub_order_number,
                    'status'                  => $so->status->value,
                    'fulfillment_model'       => $so->fulfillment_model,
                    'vendor_name'             => $so->vendor?->store_name,
                    'subtotal'                => round($so->subtotal / 100, 2),
                    'shipping'                => round($so->shipping / 100, 2),
                    'tax'                     => round($so->tax / 100, 2),
                    'tracking_number'         => $so->tracking_number,
                    'estimated_delivery_date' => $so->estimated_delivery_date?->toDateString(),
                    'sla_ship_deadline'       => $so->sla_ship_deadline?->toIso8601String(),
                    'items'                   => $so->items->map(fn ($item) => [
                        'id'                    => $item->id,
                        'product'               => $item->product_snapshot,
                        'sku'                   => $item->sku,
                        'listing_ref'           => $item->vendorListing
                            ? app(ListingIdentifierService::class)->buildListingRef($item->vendorListing)
                            : null,
                        'vendor_sku'            => $item->vendorListing?->vendor_sku,
                        'quantity'              => $item->quantity,
                        'unit_price'            => round($item->unit_price / 100, 2),
                        'line_total'            => round($item->line_total / 100, 2),
                        'fulfillment_status'    => $item->fulfillment_status->value,
                        'return_eligible_until' => $item->return_eligible_until?->toDateString(),
                    ]),
                ])
            ),
        ];
    }
}
