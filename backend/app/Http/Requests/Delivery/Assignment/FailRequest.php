<?php

namespace App\Http\Requests\Delivery\Assignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'failure_reason' => [
                'required',
                Rule::in([
                    'customer_not_home',
                    'wrong_address',
                    'customer_refused',
                    'damaged_package',
                    'other',
                ]),
            ],
            'failure_notes' => ['nullable', 'string', 'max:1000'],
            'customer_rejection_reason' => ['nullable', 'string', 'max:1000'],
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
