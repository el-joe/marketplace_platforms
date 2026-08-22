<?php

namespace App\Http\Requests\Admin;

use App\Enums\PayoutSchedule;
use App\Enums\VendorBusinessType;
use App\Enums\VendorGlobalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vendor = $this->route('vendor');

        return [
            'store_name' => ['required', 'string', 'max:150', Rule::unique('vendors', 'store_name')->ignore($vendor)],
            'store_slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9-]+$/', Rule::unique('vendors', 'store_slug')->ignore($vendor)],
            'store_description' => ['nullable', 'string', 'max:2000'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'business_type' => ['nullable', Rule::enum(VendorBusinessType::class)],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payout_schedule' => ['nullable', Rule::enum(PayoutSchedule::class)],
            'global_status' => ['nullable', Rule::enum(VendorGlobalStatus::class)],
            'account_manager_admin_id' => ['nullable', 'uuid', 'exists:admins,id'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'easy_returns_enabled' => ['nullable', 'boolean'],
            'secure_payments_enabled' => ['nullable', 'boolean'],
        ];
    }
}
