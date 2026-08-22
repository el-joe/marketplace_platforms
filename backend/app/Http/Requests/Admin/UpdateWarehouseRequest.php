<?php

namespace App\Http\Requests\Admin;

use App\Enums\WarehouseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouseId)],
            'type' => ['required', Rule::enum(WarehouseType::class)],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'owner_vendor_id' => ['nullable', 'uuid', 'exists:vendors,id'],
            'manager_admin_id' => ['nullable', 'uuid', 'exists:admins,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'total_capacity_m3' => ['nullable', 'numeric', 'min:0'],
            'used_capacity_m3' => ['nullable', 'numeric', 'min:0'],
            'storage_rate_per_m3_price' => ['nullable', 'numeric', 'min:0'],
            'storage_currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'default_limit_type' => ['nullable', Rule::in(['quantity', 'capacity'])],
            'default_max_quantity' => ['nullable', 'integer', 'min:1', 'required_if:default_limit_type,quantity'],
            'default_max_capacity_m3' => ['nullable', 'numeric', 'min:0.01', 'required_if:default_limit_type,capacity'],
            'free_storage_days' => ['nullable', 'integer', 'min:0'],
            'daily_fee_per_unit' => ['nullable', 'integer', 'min:0'],
            'daily_fee_currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('storage_rate_per_m3_price')) {
            $this->merge([
                'storage_rate_per_m3_price' => (int) round((float) $this->storage_rate_per_m3_price * 100),
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);

        // Default vendor limits only apply to platform-owned warehouses (no owner_vendor_id).
        if ($this->filled('owner_vendor_id')) {
            $this->merge([
                'default_limit_type' => null,
                'default_max_quantity' => null,
                'default_max_capacity_m3' => null,
            ]);
        } elseif ($this->input('default_limit_type') === 'quantity') {
            $this->merge(['default_max_capacity_m3' => null]);
        } elseif ($this->input('default_limit_type') === 'capacity') {
            $this->merge(['default_max_quantity' => null]);
        }
    }
}
