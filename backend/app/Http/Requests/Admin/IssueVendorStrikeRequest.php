<?php

namespace App\Http\Requests\Admin;

use App\Enums\VendorStrikeReason;
use App\Enums\VendorStrikeSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueVendorStrikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(VendorStrikeReason::class)],
            'severity' => ['required', Rule::enum(VendorStrikeSeverity::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
