<?php

namespace App\Http\Requests\Delivery\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class DeliverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp_code'                   => ['required', 'string', 'digits:6'],
            'proof_image'                => ['required', 'image', 'max:10240'],
            'latitude'                   => ['required', 'numeric', 'between:-90,90'],
            'longitude'                  => ['required', 'numeric', 'between:-180,180'],
            'cod_amount_collected' => ['nullable', 'integer', 'min:1'],
            'discrepancy_note'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
