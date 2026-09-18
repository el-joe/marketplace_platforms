<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->plan_snapshot ?? [];
        $locale = app()->getLocale();

        $orderItem = $this->whenLoaded('orderItem');
        $productSnapshot = $orderItem && $orderItem !== null ? ($this->orderItem?->product_snapshot ?? []) : [];

        // FIX-6: prefer the live product (current name/image/slug/price) and
        // fall back to the order_item snapshot only if the live product was
        // deleted or isn't loaded.
        $liveProduct = $this->whenLoaded('product');
        $liveProduct = $liveProduct instanceof \App\Models\Product ? $liveProduct : null;

        $product = $liveProduct
            ? [
                'id' => $liveProduct->id,
                'name' => $locale === 'ar'
                    ? ($liveProduct->name_ar ?? $liveProduct->name_en)
                    : ($liveProduct->name_en ?? $liveProduct->name_ar),
                'slug' => $liveProduct->slug,
                'sku' => $this->orderItem?->sku,
                'image' => $liveProduct->images?->first()?->url ?? $liveProduct->images?->first()?->path ?? null,
            ]
            : [
                'id' => null,
                'name' => $productSnapshot['name'] ?? $productSnapshot['name_en'] ?? null,
                'slug' => $productSnapshot['slug'] ?? null,
                'sku' => $this->orderItem?->sku,
                'image' => $productSnapshot['image'] ?? null,
            ];

        $isActive = $this->status === 'active';
        $isPending = $this->status === 'pending';

        return [
            'id' => $this->id,
            'status' => $this->status,
            'coverage_starts_at' => $this->coverage_starts_at?->toDateString(),
            'coverage_ends_at' => $this->coverage_ends_at?->toDateString(),
            'price_paid' => $this->price_paid,
            'currency' => $this->currency,
            'plan' => [
                'name' => $locale === 'ar'
                    ? ($snapshot['name_ar'] ?? $snapshot['name_en'] ?? null)
                    : ($snapshot['name_en'] ?? $snapshot['name_ar'] ?? null),
                'duration_months' => $snapshot['duration_months'] ?? $this->plan?->duration_months,
                'duration_label' => $this->plan?->duration_label ?? match ($snapshot['duration_months'] ?? null) {
                    1 => '1 month',
                    6 => '6 months',
                    12 => '1 year',
                    24 => '2 years',
                    default => isset($snapshot['duration_months']) ? "{$snapshot['duration_months']} months" : null,
                },
                'features' => $locale === 'ar'
                    ? ($snapshot['features_ar'] ?? $snapshot['features_en'] ?? null)
                    : ($snapshot['features_en'] ?? $snapshot['features_ar'] ?? null),
            ],
            'product' => $product,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'created_at' => $this->created_at?->toIso8601String(),
            // FIX-6: pending purchases (not yet delivered) are now returned
            // by purchases() instead of being hidden, so the frontend needs
            // an explicit signal to render them as "upcoming/not yet
            // active" rather than claimable.
            'is_upcoming' => $isPending,
            'is_claimable' => $isActive
                && $this->coverage_ends_at !== null
                && $this->coverage_ends_at->greaterThanOrEqualTo(today())
                && ! \App\Models\WarrantyClaim::where('order_item_id', $this->order_item_id)
                    ->whereNotIn('status', [
                        \App\Models\WarrantyClaim::STATUS_REJECTED,
                        \App\Models\WarrantyClaim::STATUS_RESOLVED,
                    ])
                    ->exists(),
        ];
    }
}
