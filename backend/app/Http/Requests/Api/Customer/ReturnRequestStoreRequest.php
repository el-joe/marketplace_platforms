<?php

namespace App\Http\Requests\Api\Customer;

use App\Enums\ReturnRequestReason;
use App\Enums\ReturnRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    // enhancement.md P-10: field-shape validation only. Ownership,
    // delivered/window/category/quantity eligibility, and splitting a
    // mixed-sub-order item list are all enforced once, in
    // App\Services\ReturnRequestService::create(), which is now the
    // single source of truth shared by both customer create endpoints.
}
