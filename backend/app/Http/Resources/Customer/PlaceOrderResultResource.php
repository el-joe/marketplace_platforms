<?php

namespace App\Http\Resources\Customer;

use App\Models\CustomerWallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Top-level payload for the placeOrder() success response.
 * Wraps an Order model with subOrders.items.vendorListing loaded.
 */
class PlaceOrderResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'total' => $this->total,
            'warranty_total' => $this->warranty_total,
            'currency' => $this->currency,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'sub_orders' => PlacedSubOrderResource::collection($this->subOrders),
            'wallet' => $this->walletSummary(),
        ];
    }

    private function walletSummary(): ?array
    {
        if ($this->wallet_amount_used <= 0) {
            return null;
        }

        $remainingPaid = $this->total - $this->wallet_amount_used;

        $updatedWallet = CustomerWallet::where('customer_id', $this->customer_id)->first();

        return [
            'wallet_amount_used' => $this->wallet_amount_used,
            'remaining_paid' => $remainingPaid,
            'fully_paid_by_wallet' => $remainingPaid === 0,
            'currency_code' => $this->currency,
            'new_wallet_balance' => $updatedWallet?->balance ?? 0,
        ];
    }
}
