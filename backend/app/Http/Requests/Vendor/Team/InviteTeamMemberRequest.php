<?php

namespace App\Http\Requests\Vendor\Team;

use Illuminate\Foundation\Http\FormRequest;

class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:vendor_admins,email'],
            'role'  => ['required', 'in:manager,staff'],
        ];
    }
}
