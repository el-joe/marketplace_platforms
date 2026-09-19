<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v;

        $this->merge(collect(['title_en', 'title_ar', 'description_en', 'description_ar'])
            ->filter(fn ($k) => $this->has($k))
            ->mapWithKeys(fn ($k) => [$k => $clean($this->input($k))])
            ->all());
    }

    public function rules(): array
    {
        return [
            'category_id'     => ['required', 'uuid', Rule::exists('categories', 'id')->where('is_active', true)],
            'city_id'         => ['nullable', 'uuid', Rule::exists('cities', 'id')->where('is_active', true)],
            'title_en'        => ['required', 'string', 'max:255'],
            'title_ar'        => ['nullable', 'string', 'max:255'],
            'description_en'  => ['required', 'string', 'max:5000'],
            'description_ar'  => ['nullable', 'string', 'max:5000'],
            'budget'          => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
