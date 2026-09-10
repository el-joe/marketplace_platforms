<?php

namespace App\Http\Requests\Vendor\Ads;

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
            'slot_id' => ['required', 'uuid'],
            'booked_from' => ['required', 'date'],
            'booked_until' => ['required', 'date', 'after_or_equal:booked_from'],
            'budget' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:wallet,payout_deduction'],
        ];
    }
}
