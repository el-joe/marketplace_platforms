<?php

namespace App\Http\Requests\Vendor\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $vendorId = auth('vendor')->user()?->vendor_id;

        return [
            'store_name'        => ['required', 'string', 'max:150'],
            'store_slug'        => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('vendors', 'store_slug')->ignore($vendorId),
            ],
            'store_description' => ['nullable', 'string', 'max:2000'],
            'contact_email'     => ['nullable', 'email', 'max:150'],
            'contact_phone'     => ['nullable', 'string', 'max:30'],
            'whatsapp_number'   => ['nullable', 'string', 'max:30'],
            'store_logo'        => ['nullable', 'file', 'image', 'max:2048'],
            'store_banner'      => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
