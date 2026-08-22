<?php

namespace App\Http\Requests\Vendor\Classified;

use App\Enums\ClassifiedListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClassifiedListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'      => ['nullable', Rule::enum(ClassifiedListingStatus::class)],
            'category_id' => 'nullable|exists:classified_categories,id',
            'page'        => 'nullable|integer|min:1',
        ];
    }
}
