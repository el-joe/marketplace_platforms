<?php

namespace App\Http\Resources\Customer;

use App\Services\Customer\ListingIdentifierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'order_number'             => $this->order_number,
            'status'                   => $this->status->value,
            'currency'                 => $this->currency,
            'subtotal'                 => $this->subtotal,
            'discount'                 => $this->discount,
            'shipping'                 => $this->shipping,
            'tax'                      => $this->tax,
            'cod_fee'                  => $this->cod_fee,
            'total'                    => $this->total,
            'coupon_code_used'         => $this->coupon_code_used,
            'payment_method'           => $this->payment_method,
            'payment_status'           => $this->payment_status?->value,
            'shipping_address'         => $this->shipping_address_snapshot,
            'customer_notes'           => $this->customer_notes,
            'placed_at'                => $this->placed_at?->toIso8601String(),
            'completed_at'             => $this->completed_at?->toIso8601String(),
            'cancelled_at'             => $this->cancelled_at?->toIso8601String(),
            'sub_orders'               => $this->whenLoaded('subOrders', fn () =>
                $this->subOrders->map(fn ($so) => [
                    'id'                     => $so->id,
                    'sub_order_number'       => $so->sub_order_number,
                    'status'                 => $so->status->value,
                    'fulfillment_model'      => $so->fulfillment_model,
                    'vendor_name'            => $so->vendor?->name,
                    'tracking_number'        => $so->tracking_number,
                    'carrier_name'           => $so->carrier?->name,
                    'estimated_delivery_date' => $so->estimated_delivery_date?->toDateString(),
                    'shipped_at'             => $so->shipped_at?->toIso8601String(),
                    'delivered_at'           => $so->delivered_at?->toIso8601String(),
                    'subtotal'               => $so->subtotal,
                    'shipping'               => $so->shipping,
                    'tax'                    => $so->tax,
                    'items'                  => $so->items->map(fn ($item) => [
                        'id'               => $item->id,
                        'sku'              => $item->sku,
                        'listing_ref'      => $item->vendorListing
                            ? app(ListingIdentifierService::class)->buildListingRef($item->vendorListing)
                            : null,
                        'vendor_sku'       => $item->vendorListing?->vendor_sku,
                        'product_snapshot' => $item->product_snapshot,
                        'quantity'         => $item->quantity,
                        'unit_price'       => $item->unit_price,
                        'line_total'       => $item->line_total,
                        'fulfillment_status' => $item->fulfillment_status->value,
                        'return_eligible_until' => $item->return_eligible_until?->toDateString(),
                    ]),
                    'status_history' => $so->statusHistories->map(fn ($h) => [
                        'from'       => $h->from_status,
                        'to'         => $h->to_status,
                        'reason'     => $h->reason,
                        'created_at' => $h->created_at?->toIso8601String(),
                    ]),
                ])
            ),
            'status_history'           => $this->whenLoaded('statusHistories', fn () =>
                $this->statusHistories->map(fn ($h) => [
                    'from'       => $h->from_status,
                    'to'         => $h->to_status,
                    'reason'     => $h->reason,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])
            ),
            'payment_summary'          => $this->whenLoaded('transactions', fn () =>
                $this->transactions->map(fn ($t) => [
                    'type'       => $t->type?->value,
                    'amount'     => $t->amount,
                    'currency'   => $t->currency,
                    'status'     => $t->status->value,
                    'gateway'    => $t->gateway,
                    'processed_at' => $t->processed_at?->toIso8601String(),
                ])
            ),
        ];
    }
}
