<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ProductListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'          => ['nullable'],
            'brand'             => ['nullable'],
            'price_min'         => ['nullable', 'numeric', 'min:0'],
            'price_max'         => ['nullable', 'numeric', 'min:0'],
            'rating_min'        => ['nullable', 'numeric', 'between:1,5'],
            'condition'         => ['nullable', 'in:new,like_new,good,acceptable,refurbished'],
            'fulfillment_model' => ['nullable', 'in:fbm,fbn,cross_dock'],
            'sort'              => ['nullable', 'in:relevance,price_asc,price_desc,rating,newest,best_selling'],
            'include_oos'       => ['nullable', 'boolean'],
            'page'              => ['nullable', 'integer', 'min:1'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:100'],
            'attributes'        => ['nullable', 'array'],
            'attributes.*'      => ['array'],
        ];
    }
}
