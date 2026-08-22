<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class ReturnMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'          => ['required', 'string', 'max:5000'],
            'attachments'      => ['nullable', 'array', 'max:5'],
            'attachments.*'    => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,mov'],
        ];
    }
}
