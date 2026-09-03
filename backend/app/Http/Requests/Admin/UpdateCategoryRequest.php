<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category');

        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', Rule::exists('categories', 'id')->whereNot('id', $id)],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', Rule::unique('categories', 'slug')->ignore($id)],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbp_pct' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbp_fixed' => ['nullable', 'integer', 'min:0'],
            'commission_fbn_pct' => ['nullable', 'numeric', 'between:0,100'],
            'commission_fbn_fixed' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_visible' => ['boolean'],
            'is_featured' => ['boolean'],
            'has_filters' => ['boolean'],
            'seo_title_en' => ['nullable', 'string', 'max:70'],
            'seo_title_ar' => ['nullable', 'string', 'max:70'],
            'seo_description_en' => ['nullable', 'string', 'max:160'],
            'seo_description_ar' => ['nullable', 'string', 'max:160'],
            'influencer_sample_qty' => ['nullable', 'integer', 'min:0'],
            'affiliate_sample_qty' => ['nullable', 'integer', 'min:0'],
            'platform_sample_qty' => ['nullable', 'integer', 'min:0'],
            'min_stock_for_campaign' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_en' => 'English name',
            'name_ar' => 'Arabic name',
            'commission_rate' => 'commission rate',
            'parent_id' => 'parent category',
        ];
    }
}
