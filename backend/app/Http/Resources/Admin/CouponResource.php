<?php

namespace App\Http\Resources\Admin;

use App\Services\Admin\CouponService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'bank_name' => $this->bank_name,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'terms_ar' => $this->terms_ar,
            'terms_en' => $this->terms_en,
            'max_orders_per_customer_per_month' => $this->max_orders_per_customer_per_month,
            'is_bank_offer' => $this->isBankOffer(),
            'type' => $this->type->value,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'scope' => $this->scope->value,
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->whenLoaded('vendor', fn () => $this->vendor?->store_name),
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name_en),
            'min_order_amount' => $this->min_order_amount,
            'max_discount' => $this->max_discount,
            'usage_limit_total' => $this->usage_limit_total,
            'usage_limit_per_customer' => $this->usage_limit_per_customer,
            'times_used' => $this->times_used,
            'remaining_capacity' => $this->usage_limit_total !== null
                ? max(0, $this->usage_limit_total - $this->times_used)
                : null,
            'customer_eligibility' => $this->customer_eligibility->value,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'is_stackable' => (bool) $this->is_stackable,
            'is_admin_managed' => in_array($this->scope->value, CouponService::ADMIN_MANAGEABLE_SCOPES, true),
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
