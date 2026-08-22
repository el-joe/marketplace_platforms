<?php

namespace App\Http\Requests\Vendor;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required',
                'numeric',
                'min:0',
                Rule::when(fn () => $this->input('type') === 'percentage', ['max:100', 'gt:0']),
                Rule::when(fn () => $this->input('type') === 'fixed_amount', ['gt:0']),
            ],
            'currency' => [
                Rule::when(fn () => in_array($this->input('type'), ['fixed_amount', 'bogo'], true), ['required'], ['nullable']),
                'string', 'size:3',
            ],
            // Vendors may only create vendor/product scoped coupons; platform/category
            // scopes belong to the admin panel.
            'scope' => ['required', Rule::in(['vendor', 'product'])],
            'product_ids' => [
                Rule::when(fn () => $this->input('scope') === 'product', ['required', 'array', 'min:1'], ['nullable']),
            ],
            'product_ids.*' => ['uuid', 'exists:products,id'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'is_active' => ['boolean'],
            'is_stackable' => ['boolean'],
        ];
    }
}
