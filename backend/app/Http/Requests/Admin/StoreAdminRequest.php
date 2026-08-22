<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', 'unique:admins,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'status' => ['required', Rule::enum(AdminStatus::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
