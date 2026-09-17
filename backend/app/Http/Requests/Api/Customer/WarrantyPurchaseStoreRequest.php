<?php

namespace App\Http\Requests\Api\Customer;

use App\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * enhancement.md P-09 task 4: post-purchase "buy a warranty after
 * delivery" flow. Validates ownership, delivery, the purchase window and
 * that the item doesn't already have a pending/active warranty, mirroring
 * WarrantyClaimStoreRequest's withValidator() pattern.
 */
class WarrantyPurchaseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'uuid', 'exists:order_items,id'],
            'warranty_plan_id' => ['required', 'uuid', 'exists:warranty_plans,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customer = auth('customer')->user();

            $orderItem = OrderItem::with(['order', 'subOrder.vendor', 'productVariant.product', 'warrantyPurchase'])
                ->find($this->input('order_item_id'));

            if (! $orderItem || ! $orderItem->order || $orderItem->order->customer_id !== $customer->id) {
                $validator->errors()->add('order_item_id', 'The selected order item is invalid.');

                return;
            }

            $deliveredAt = $orderItem->subOrder?->delivered_at;
            $fulfillmentStatus = $orderItem->fulfillment_status instanceof \BackedEnum
                ? $orderItem->fulfillment_status->value
                : $orderItem->fulfillment_status;

            if (! $deliveredAt || $fulfillmentStatus !== 'delivered') {
                $validator->errors()->add('order_item_id', 'This item has not been delivered yet.');

                return;
            }

            $windowDays = (int) config('warranty.post_purchase_window_days', 30);

            if (now()->gt($deliveredAt->copy()->addDays($windowDays))) {
                $validator->errors()->add('order_item_id', "Warranties can only be purchased within {$windowDays} days of delivery.");

                return;
            }

            $existingPurchase = $orderItem->warrantyPurchase;

            if ($existingPurchase && in_array($existingPurchase->status, ['pending', 'active'], true)) {
                $validator->errors()->add('order_item_id', 'This item already has a pending or active warranty.');

                return;
            }

            $plan = \App\Models\WarrantyPlan::find($this->input('warranty_plan_id'));

            if (! $plan || ! $plan->is_active) {
                $validator->errors()->add('warranty_plan_id', 'The selected warranty plan is unavailable.');

                return;
            }

            $product = $orderItem->productVariant?->product;

            if (! $product) {
                $validator->errors()->add('warranty_plan_id', 'The selected warranty plan does not apply to this item.');

                return;
            }

            // Reuse WarrantyPlanService's own category (incl. ancestors) and
            // country resolution instead of duplicating it here, so this
            // matches exactly what GET warranty/plans/{orderItemId} offered.
            $availablePlanIds = collect(app(\App\Services\WarrantyPlanService::class)->getPlansForProduct(
                $product,
                $customer->country_id,
                $orderItem->order->currency,
                (int) $orderItem->unit_price,
            ))->pluck('id')->all();

            if (! in_array($plan->id, $availablePlanIds, true)) {
                $validator->errors()->add('warranty_plan_id', 'The selected warranty plan does not apply to this item.');
            }
        });
    }
}
