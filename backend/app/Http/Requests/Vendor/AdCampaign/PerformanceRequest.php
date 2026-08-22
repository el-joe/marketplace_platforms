<?php

namespace App\Http\Requests\Vendor\AdCampaign;

use Illuminate\Foundation\Http\FormRequest;

class PerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_listing' => ['nullable', 'boolean'],
        ];
    }
}
