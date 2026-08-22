<?php

namespace App\Http\Requests\Vendor\Classified;

use Illuminate\Foundation\Http\FormRequest;

class AcceptClassifiedContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_data' => 'required|string', // base64-encoded signature-pad image
        ];
    }
}
