<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_warehouse_id'      => ['required', 'uuid', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.vendor_listing_id' => ['required', 'uuid', 'exists:vendor_listings,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'expected_arrival_date'    => ['nullable', 'date', 'after:today'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
