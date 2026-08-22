<?php

namespace App\Http\Resources\Vendor;

use App\Models\VendorListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorCouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'scope' => $this->scope->value,
            'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->values()),
            'min_order_amount' => $this->min_order_amount,
            'max_discount' => $this->max_discount,
            'usage_limit_total' => $this->usage_limit_total,
            'usage_limit_per_customer' => $this->usage_limit_per_customer,
            'times_used' => $this->times_used,
            'remaining_capacity' => $this->usage_limit_total !== null
                ? max(0, $this->usage_limit_total - $this->times_used)
                : null,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'is_stackable' => (bool) $this->is_stackable,
            'estimated_reach' => $this->estimatedReach(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function estimatedReach(): int
    {
        if ($this->scope->value === 'product') {
            $productIds = $this->relationLoaded('products')
                ? $this->products->pluck('id')
                : $this->products()->pluck('products.id');

            if ($productIds->isEmpty()) {
                return 0;
            }

            return VendorListing::query()
                ->where('vendor_id', $this->vendor_id)
                ->where('status', 'active')
                ->whereHas('productVariant', fn ($q) => $q->whereIn('product_id', $productIds))
                ->count();
        }

        return VendorListing::query()
            ->where('vendor_id', $this->vendor_id)
            ->where('status', 'active')
            ->count();
    }
}
