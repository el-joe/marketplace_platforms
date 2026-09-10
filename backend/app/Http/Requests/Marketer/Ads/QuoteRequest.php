<?php

namespace App\Http\Requests\Marketer\Ads;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booked_from' => ['required', 'date'],
            'booked_until' => ['required', 'date', 'after_or_equal:booked_from'],
            'budget' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
