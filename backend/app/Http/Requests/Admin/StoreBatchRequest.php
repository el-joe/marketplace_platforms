<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency_code' => ['required', Rule::in(['SAR', 'AED', 'EGP', 'KWD', 'OMR', 'QAR', 'BHD', 'JOD'])],
            'amount' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'activate_immediately' => ['nullable', 'boolean'],
            'is_purchasable' => ['boolean'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'min_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
