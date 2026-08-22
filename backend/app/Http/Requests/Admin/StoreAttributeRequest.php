<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z_]+$/', 'unique:attributes,code'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_variant_attribute' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_required' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_en' => 'English name',
            'name_ar' => 'Arabic name',
            'code' => 'attribute code',
            'type' => 'attribute type',
        ];
    }
}
