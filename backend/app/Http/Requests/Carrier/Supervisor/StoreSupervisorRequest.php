<?php

namespace App\Http\Requests\Carrier\Supervisor;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupervisorRequest extends FormRequest
{
    private const ALLOWED_PERMISSIONS = ['manage_agents', 'view_orders', 'assign_orders', 'view_reports'];

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:shipping_company_supervisors,email'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'country_id'      => ['nullable', 'string', 'exists:countries,id'],
            'permissions'     => ['required', 'array', 'min:1'],
            'permissions.*'   => ['string', 'in:' . implode(',', self::ALLOWED_PERMISSIONS)],
        ];
    }
}
