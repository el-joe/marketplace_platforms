<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartCardOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'card_name_en' => ['required', 'string', 'max:150'],
            'card_name_ar' => ['required', 'string', 'max:150'],
            'bank_name_en' => ['nullable', 'string', 'max:100'],
            'bank_name_ar' => ['nullable', 'string', 'max:100'],
            'card_image' => ['nullable', 'image', 'max:2048'],
            'cashback_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'cashback_pct' => [
                Rule::when(fn () => $this->input('cashback_type') === 'percentage', ['required'], ['nullable']),
                'numeric', 'min:0', 'max:100',
            ],
            'cashback_fixed_amount' => [
                Rule::when(fn () => $this->input('cashback_type') === 'fixed', ['required'], ['nullable']),
                'integer', 'min:0',
            ],
            'label_template_en' => ['required', 'string', 'max:300'],
            'label_template_ar' => ['nullable', 'string', 'max:300'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'apply_label_en' => ['nullable', 'string', 'max:100'],
            'apply_label_ar' => ['nullable', 'string', 'max:100'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_cashback_amount' => ['nullable', 'integer', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
