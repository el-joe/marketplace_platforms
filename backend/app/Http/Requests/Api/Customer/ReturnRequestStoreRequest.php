<?php

namespace App\Http\Requests\Api\Customer;

use App\Enums\ReturnRequestReason;
use App\Enums\ReturnRequestType;
use App\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReturnRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_ids' => ['required', 'array', 'min:1'],
            'order_item_ids.*' => ['required', 'uuid', 'exists:order_items,id'],
            'reason' => ['required', Rule::enum(ReturnRequestReason::class)],
            'reason_description' => ['nullable', 'string', 'max:2000'],
            'return_type' => ['required', Rule::enum(ReturnRequestType::class)],
            'pickup_address_id' => ['nullable', 'uuid', 'exists:addresses,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customer = auth('customer')->user();
            $itemIds = $this->input('order_item_ids', []);

            if (! is_array($itemIds) || $itemIds === []) {
                return;
            }

            $items = OrderItem::with('order')->whereIn('id', $itemIds)->get();

            if ($items->count() !== count($itemIds)) {
                $validator->errors()->add('order_item_ids', 'One or more order items are invalid.');

                return;
            }

            foreach ($items as $item) {
                if (! $item->order || $item->order->customer_id !== $customer->id) {
                    $validator->errors()->add('order_item_ids', 'One or more order items do not belong to you.');

                    return;
                }

                if ($item->fulfillment_status?->value !== 'delivered') {
                    $validator->errors()->add('order_item_ids', 'Only delivered items can be returned.');

                    return;
                }

                if (! $item->return_eligible_until || $item->return_eligible_until->lt(today())) {
                    $validator->errors()->add('order_item_ids', 'The return window for one or more items has expired.');

                    return;
                }
            }

            $subOrderIds = $items->pluck('sub_order_id')->unique();

            if ($subOrderIds->count() > 1) {
                $validator->errors()->add('order_item_ids', 'All items must belong to the same sub-order.');
            }
        });
    }
}
