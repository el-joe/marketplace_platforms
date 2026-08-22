<?php

namespace App\Http\Requests\Vendor\AdCampaign;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $targetingType = $this->input('targeting_type');

        return [
            'name'                          => ['sometimes', 'string', 'max:200'],
            'type'                          => ['sometimes', 'in:cpc,cpm'],
            'targeting_type'                => ['sometimes', 'in:auto,keyword,category,mixed'],
            'budget_total'                  => ['sometimes', 'numeric', 'min:1'],
            'budget_daily'                  => ['nullable', 'numeric', 'min:1'],
            'bid'                           => ['sometimes', 'numeric', 'min:0.01'],
            'starts_at'                     => ['sometimes', 'date'],
            'ends_at'                       => ['nullable', 'date', 'after:starts_at'],
            'country_id'                    => ['sometimes', 'uuid', 'exists:countries,id'],
            'product_variant_ids'           => ['sometimes', 'array', 'min:1'],
            'product_variant_ids.*'         => ['required_with:product_variant_ids', 'uuid'],
            'keywords'                      => ['nullable', 'array'],
            'keywords.*.keyword'            => ['required_with:keywords', 'string', 'max:150'],
            'keywords.*.match_type'         => ['required_with:keywords', 'in:broad,phrase,exact'],
            'keywords.*.bid_override'       => ['nullable', 'numeric', 'min:0.01'],
            'category_ids'                  => ['nullable', 'array'],
            'category_ids.*.category_id'    => ['required_with:category_ids', 'uuid', 'exists:categories,id'],
            'category_ids.*.bid_override'   => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
