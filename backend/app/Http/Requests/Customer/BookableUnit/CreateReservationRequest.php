<?php

namespace App\Http\Requests\Customer\BookableUnit;

use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'includes_overnight' => ['nullable', 'boolean'],
            'time_slot_id' => ['nullable', 'uuid'],
        ];
    }
}
