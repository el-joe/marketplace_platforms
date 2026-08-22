<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class InventoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'listing_id'   => ['nullable', 'uuid', 'exists:vendor_listings,id'],
            'low_stock'    => ['nullable', 'boolean'],
            'page'         => ['nullable', 'integer', 'min:1'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
