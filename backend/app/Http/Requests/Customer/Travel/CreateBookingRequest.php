<?php

namespace App\Http\Requests\Customer\Travel;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'travelers_count' => ['required', 'integer', 'min:1', 'max:10'],
            'passport_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'unit_id' => ['nullable', 'uuid'],
            'unit_days' => ['nullable', 'array'],
            'unit_days.*.date' => ['required_with:unit_days', 'date', 'after_or_equal:today'],
            'unit_days.*.includes_overnight' => ['nullable', 'boolean'],
            'unit_days.*.time_slot_id' => ['nullable', 'uuid'],
        ];
    }
}
