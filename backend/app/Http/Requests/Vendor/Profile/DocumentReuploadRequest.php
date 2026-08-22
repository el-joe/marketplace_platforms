<?php

namespace App\Http\Requests\Vendor\Profile;

use Illuminate\Foundation\Http\FormRequest;

class DocumentReuploadRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}
