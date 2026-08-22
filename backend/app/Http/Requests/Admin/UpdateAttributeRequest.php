<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('attribute');

        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            // code and type are locked after creation; ignored on update
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
        ];
    }
}
