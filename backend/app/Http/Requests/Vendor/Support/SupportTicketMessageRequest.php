<?php

namespace App\Http\Requests\Vendor\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketMessageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'message'    => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ];
    }
}
