<?php

namespace App\Http\Requests\Vendor\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'exists:vendor_document_types,code,is_active,1'],
            'file'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}
