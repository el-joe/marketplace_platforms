<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:200'],
            'name_ar' => ['required', 'string', 'max:200'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'submission_opens_at' => ['required', 'date', 'after:now'],
            'submission_closes_at' => ['required', 'date', 'after:submission_opens_at'],
            'review_deadline_at' => ['required', 'date', 'after:submission_closes_at'],
            'sale_starts_at' => ['required', 'date', 'after:review_deadline_at'],
            'sale_ends_at' => ['required', 'date', 'after:sale_starts_at'],
            'min_discount_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_products_per_seller' => ['nullable', 'integer', 'min:1'],
            'eligible_categories' => ['nullable', 'array'],
            'eligible_categories.*' => ['exists:categories,id'],
            'eligible_seller_tiers' => ['nullable', 'array'],
            'eligible_seller_tiers.*' => ['in:bronze,silver,gold,platinum'],
            'min_seller_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'commission_override_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_featured' => ['boolean'],
            'is_exclusive' => ['boolean'],
            'price_drop_required' => ['boolean'],
            'max_total_slots' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
