<?php

namespace App\Http\Requests\Vendor\AdCampaign;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $targetingType = $this->input('targeting_type');

        return [
            'name'                          => ['required', 'string', 'max:200'],
            'type'                          => ['required', 'in:cpc,cpm'],
            'targeting_type'                => ['required', 'in:auto,keyword,category,mixed'],
            'budget_total'                  => ['required', 'numeric', 'min:1'],
            'budget_daily'                  => ['nullable', 'numeric', 'min:1', 'lte:budget_total'],
            'bid'                           => ['required', 'numeric', 'min:0.01'],
            'starts_at'                     => ['required', 'date', 'after_or_equal:today'],
            'ends_at'                       => ['nullable', 'date', 'after:starts_at'],
            'country_id'                    => ['required', 'uuid', 'exists:countries,id'],
            'product_variant_ids'           => ['required', 'array', 'min:1'],
            'product_variant_ids.*'         => ['required', 'uuid'],

            // keywords — required when targeting_type includes keyword
            'keywords'                      => [
                in_array($targetingType, ['keyword', 'mixed']) ? 'required' : 'nullable',
                'array', 'min:1',
            ],
            'keywords.*.keyword'            => ['required_with:keywords', 'string', 'max:150'],
            'keywords.*.match_type'         => ['required_with:keywords', 'in:broad,phrase,exact'],
            'keywords.*.bid_override'       => ['nullable', 'numeric', 'min:0.01'],

            // category_ids — required when targeting_type includes category
            'category_ids'                  => [
                in_array($targetingType, ['category', 'mixed']) ? 'required' : 'nullable',
                'array', 'min:1',
            ],
            'category_ids.*.category_id'    => ['required_with:category_ids', 'uuid', 'exists:categories,id'],
            'category_ids.*.bid_override'   => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variant_ids.required' => 'يجب اختيار منتج واحد على الأقل.',
            'keywords.required'            => 'الكلمات المفتاحية مطلوبة لنوع الاستهداف المختار.',
            'category_ids.required'        => 'الفئات مطلوبة لنوع الاستهداف المختار.',
            'budget_daily.lte'             => 'الميزانية اليومية يجب ألا تتجاوز الميزانية الإجمالية.',
        ];
    }
}
