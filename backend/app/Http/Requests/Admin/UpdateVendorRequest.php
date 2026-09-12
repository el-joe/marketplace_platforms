<?php

namespace App\Http\Requests\Admin;

use App\Enums\PayoutSchedule;
use App\Enums\VendorBusinessType;
use App\Enums\VendorCommissionDiscountType;
use App\Enums\VendorGlobalStatus;
use App\Enums\VendorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'commission_discount_type' => ['nullable', Rule::enum(VendorCommissionDiscountType::class)],
            'commission_discount_flat' => ['nullable', 'integer', 'min:0'],
            'commission_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_discount_notes' => ['nullable', 'string', 'max:2000'],
            'payout_schedule' => ['nullable', Rule::enum(PayoutSchedule::class)],
            'global_status' => ['nullable', Rule::enum(VendorGlobalStatus::class)],
            'vendor_type' => ['nullable', Rule::enum(VendorType::class)],
            'account_manager_admin_id' => ['nullable', 'uuid', 'exists:admins,id'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'easy_returns_enabled' => ['nullable', 'boolean'],
            'secure_payments_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vendor = $this->route('vendor');

            if (!$vendor || !$this->filled('vendor_type')) {
                return;
            }

            if ($this->input('vendor_type') === $vendor->vendor_type?->value) {
                return;
            }

            $hasProductListings = $vendor->listings()->exists();
            $hasClassifiedListings = $vendor->classifiedListings()->exists();

            if ($hasProductListings || $hasClassifiedListings) {
                $validator->errors()->add(
                    'vendor_type',
                    'Vendor type cannot be changed once the vendor has product listings or classified listings.'
                );
            }
        });
    }
}
