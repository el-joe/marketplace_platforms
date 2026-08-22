<?php

namespace App\Http\Requests\Customer\Return;

use App\Enums\ReturnRequestReason;
use App\Enums\ReturnRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'order_item_ids'   => ['required', 'array', 'min:1'],
            'order_item_ids.*' => ['required', 'uuid', 'exists:order_items,id'],
            'reason'           => ['required', Rule::enum(ReturnRequestReason::class)],
            'return_type'      => ['required', Rule::enum(ReturnRequestType::class)],
            'comments'         => ['nullable', 'string', 'max:2000'],
        ];
    }
}
