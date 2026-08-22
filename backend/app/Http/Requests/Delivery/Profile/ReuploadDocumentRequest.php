<?php

namespace App\Http\Requests\Delivery\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ReuploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
