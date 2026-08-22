<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $admin = $this->route('admin');
        $adminId = $admin instanceof \App\Models\Admin ? $admin->id : $admin;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', Rule::unique('admins', 'email')->ignore($adminId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'status' => ['required', Rule::enum(AdminStatus::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'vendors_assigned_only' => ['sometimes', 'boolean'],
        ];
    }
}
