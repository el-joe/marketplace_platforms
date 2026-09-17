<?php

namespace App\Http\Requests\Api\Customer;

use App\Models\OrderItem;
use App\Models\WarrantyClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WarrantyClaimStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'uuid', 'exists:order_items,id'],
            'issue_type' => [
                'required',
                Rule::in(['defective', 'not_working', 'physical_damage', 'missing_parts', 'software_issue', 'other']),
            ],
            'issue_description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_files' => ['sometimes', 'array', 'max:5'],
            'evidence_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customer = auth('customer')->user();

            $orderItem = OrderItem::with(['order', 'warrantyPurchase', 'subOrder.vendor'])
                ->find($this->input('order_item_id'));

            if (! $orderItem || ! $orderItem->order || $orderItem->order->customer_id !== $customer->id) {
                $validator->errors()->add('order_item_id', 'The selected order item is invalid.');

                return;
            }

            $deliveredAt = $orderItem->subOrder?->delivered_at;

            if (! $deliveredAt) {
                $validator->errors()->add('order_item_id', 'This item has not been delivered yet.');

                return;
            }

            $warrantyPurchase = $orderItem->warrantyPurchase;

            // enhancement.md P-09 task 5: a claim can be covered by the
            // platform warranty (an active warranty_purchases row within its
            // own coverage window) or, absent one, by the brand/vendor
            // warranty window (delivered_at + vendors.warranty_months). Only
            // reject when NEITHER window covers today.
            $withinPlatformWindow = $warrantyPurchase
                && $warrantyPurchase->status === 'active'
                && $warrantyPurchase->coverage_ends_at
                && $warrantyPurchase->coverage_ends_at->gte(today());

            $vendorWarrantyMonths = $orderItem->subOrder?->vendor?->warranty_months;
            $brandWindowEnds = $vendorWarrantyMonths
                ? $deliveredAt->copy()->addMonths((int) $vendorWarrantyMonths)
                : null;
            $withinBrandWindow = $brandWindowEnds && $brandWindowEnds->gte(today());

            if (! $withinPlatformWindow && ! $withinBrandWindow) {
                $validator->errors()->add('order_item_id', 'This item is outside every warranty window (platform and brand).');

                return;
            }

            $hasOpenClaim = WarrantyClaim::where('order_item_id', $orderItem->id)
                ->whereNotIn('status', [WarrantyClaim::STATUS_REJECTED, WarrantyClaim::STATUS_RESOLVED])
                ->exists();

            if ($hasOpenClaim) {
                $validator->errors()->add('order_item_id', 'There is already an open warranty claim for this item.');
            }
        });
    }
}
