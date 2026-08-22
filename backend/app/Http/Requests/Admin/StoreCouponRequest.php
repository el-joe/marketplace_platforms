<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponCustomerEligibility;
use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['terms_ar', 'terms_en'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([
                    $field => array_values(array_filter(array_map('trim', explode("\n", $this->input($field))))),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'terms_ar' => ['nullable', 'array'],
            'terms_ar.*' => ['string', 'max:500'],
            'terms_en' => ['nullable', 'array'],
            'terms_en.*' => ['string', 'max:500'],
            'max_orders_per_customer_per_month' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required',
                'numeric',
                'min:0',
                Rule::when(fn () => $this->input('type') === 'percentage', ['max:100']),
                Rule::when(fn () => $this->input('type') === 'fixed_amount', ['gt:0']),
            ],
            'currency' => [
                Rule::when(fn () => in_array($this->input('type'), ['fixed_amount', 'bogo'], true), ['required'], ['nullable']),
                'string', 'size:3',
            ],
            // Admin panel only manages platform/category scoped coupons; vendor/product
            // scopes belong to the vendor panel and are read-only here.
            'scope' => ['required', Rule::in(['platform', 'category'])],
            'category_id' => [
                Rule::when(fn () => $this->input('scope') === 'category', ['required'], ['nullable']),
                'uuid', 'exists:categories,id',
            ],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['required', 'integer', 'min:1'],
            'customer_eligibility' => ['required', Rule::enum(CouponCustomerEligibility::class)],
            'eligible_customer_ids' => [
                Rule::when(fn () => $this->input('customer_eligibility') === 'specific_users', ['required']),
                'array',
            ],
            'eligible_customer_ids.*' => ['uuid', 'exists:customers,id'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['uuid', 'exists:countries,id'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'is_active' => ['boolean'],
            'is_stackable' => ['boolean'],
            'funded_by' => ['required', Rule::in(['platform', 'shared'])],
            'vendor_share_pct' => [
                Rule::when(fn () => $this->input('funded_by') === 'shared', ['required'], ['nullable']),
                'integer', 'min:0', 'max:100',
            ],
        ];
    }
}
