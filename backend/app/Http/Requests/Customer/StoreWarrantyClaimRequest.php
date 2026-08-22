<?php

namespace App\Http\Requests\Customer;

use App\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'uuid'],
            'issue_type' => [
                'required',
                Rule::in([
                    'defective',
                    'not_working',
                    'physical_damage',
                    'missing_parts',
                    'software_issue',
                    'other',
                ]),
            ],
            'issue_description' => ['required', 'string', 'min:20', 'max:2000'],
            'warranty_expires_at' => [
                Rule::requiredIf(function () {
                    $orderItem = OrderItem::find($this->input('order_item_id'));

                    return !$orderItem
                        || !$orderItem->warranty_purchase_id
                        || $orderItem->warrantyPurchase?->status !== 'active';
                }),
                'date',
            ],
            'evidence_files' => ['sometimes', 'array', 'max:5'],
            'evidence_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customer = auth('customer')->user();

            $orderItem = OrderItem::with('order')
                ->find($this->input('order_item_id'));

            if (!$orderItem || !$orderItem->order || $orderItem->order->customer_id !== $customer->id) {
                $validator->errors()->add('order_item_id', 'The selected order item is invalid.');

                return;
            }

            if ($orderItem->order->status?->value !== 'delivered') {
                $validator->errors()->add('order_item_id', 'Warranty claims can only be filed for delivered orders.');
            }
        });
    }
}
