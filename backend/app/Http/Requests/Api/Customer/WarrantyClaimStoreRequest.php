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

            $orderItem = OrderItem::with(['order', 'warrantyPurchase'])
                ->find($this->input('order_item_id'));

            if (! $orderItem || ! $orderItem->order || $orderItem->order->customer_id !== $customer->id) {
                $validator->errors()->add('order_item_id', 'The selected order item is invalid.');

                return;
            }

            $warrantyPurchase = $orderItem->warrantyPurchase;

            if (! $warrantyPurchase || $warrantyPurchase->status !== 'active') {
                $validator->errors()->add('order_item_id', 'This item is not covered by an active warranty.');

                return;
            }

            if (! $warrantyPurchase->coverage_ends_at || $warrantyPurchase->coverage_ends_at->lt(today())) {
                $validator->errors()->add('order_item_id', 'The warranty coverage for this item has expired.');

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
