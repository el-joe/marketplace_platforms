<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cart_id'  => $this->id,
            'session_token' => $this->when(
                $this->session_token !== null,
                $this->session_token
            ),
            'currency' => $this->currency,
            'summary'  => [
                'subtotal'           => $this->subtotal,
                'discount'           => $this->discount,
                'wallet_amount_to_use' => $this->wallet_amount_to_use,
                'estimated_shipping' => $this->estimated_shipping,
                'estimated_tax'      => $this->estimated_tax,
                'estimated_total'    => $this->estimated_total,
                'item_count'               => $this->items->count(),
            ],
            'coupon' => $this->coupon ? [
                'code'        => $this->coupon->code,
                'type'        => $this->coupon->type?->value,
                'description' => $this->coupon->description,
            ] : null,
            // Set (transient, non-persisted) by CartService::recalculateCart()
            // when a previously-applied coupon fails re-validation on this
            // load and gets detached — surfaces why the coupon disappeared
            // instead of the cart silently showing discount: 0.
            'coupon_error' => $this->when(
                $this->offsetExists('coupon_error') && $this->coupon_error !== null,
                fn () => $this->coupon_error
            ),
            'items'      => (function () {
                \App\Services\Customer\PromoBadgeResolver::instance()->prime(
                    $this->items->map(function ($i) {
                        $l = $i->marketer_listing_id ? $i->marketerListing : ($i->admin_listing_id ? $i->adminListing : $i->vendorListing);
                        return $l ? [
                            $i->marketer_listing_id ? 'marketer' : ($i->admin_listing_id ? 'admin' : 'vendor'),
                            $l->id,
                            $l->productVariant?->product_id,
                        ] : null;
                    })->filter()->values()
                );
                return CartItemResource::collection($this->items);
            })(),
            'expires_at' => $this->expires_at,
        ];
    }
}
