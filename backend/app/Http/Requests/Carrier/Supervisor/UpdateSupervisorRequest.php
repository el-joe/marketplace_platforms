<?php

namespace App\Http\Requests\Carrier\Supervisor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupervisorRequest extends FormRequest
{
    private const ALLOWED_PERMISSIONS = ['manage_agents', 'view_orders', 'assign_orders', 'view_reports'];

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'permissions'                => ['sometimes', 'array'],
            'permissions.*'              => ['string', 'in:' . implode(',', self::ALLOWED_PERMISSIONS)],
            'is_active'                  => ['sometimes', 'boolean'],
            'receives_all_notifications' => ['sometimes', 'boolean'],
        ];
    }
}
