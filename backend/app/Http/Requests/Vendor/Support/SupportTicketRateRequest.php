<?php

namespace App\Http\Requests\Vendor\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'satisfaction_rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'satisfaction_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
