<?php

namespace App\Http\Requests\Customer\Classified;

use Illuminate\Foundation\Http\FormRequest;

class SignCustomerContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signer_name' => ['required', 'string', 'min:2', 'max:150'],
            'accept' => ['required', 'accepted'],
            'template_id' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:1'],
            'language' => ['nullable', 'in:en,ar'],
        ];
    }
}
