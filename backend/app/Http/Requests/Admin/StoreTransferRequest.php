<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'vendor_id' => ['nullable', 'uuid', 'exists:vendors,id'],
            'expected_arrival_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.vendor_listing_id' => ['required', 'uuid', 'exists:vendor_listings,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_warehouse_id.different' => 'Source and destination warehouse must be different.',
        ];
    }
}
