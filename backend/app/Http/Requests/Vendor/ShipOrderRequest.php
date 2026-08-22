<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class ShipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:100'],
            'carrier_id'      => ['nullable', 'uuid', 'exists:shipping_carriers,id'],
        ];
    }
}
