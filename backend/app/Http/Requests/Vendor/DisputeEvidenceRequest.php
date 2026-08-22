<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class DisputeEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'        => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,mp4,mov,avi'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
