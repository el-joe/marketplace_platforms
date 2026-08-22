<?php

namespace App\Http\Requests\Partner;

use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_warehouse_id'      => ['required', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'expected_arrival_date'    => ['nullable', 'date', 'after:today'],
            'notes'                    => ['nullable', 'string'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.vendor_listing_id' => ['required', 'exists:vendor_listings,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $vendorId = auth('vendor')->user()->vendor_id;
            $sourceWarehouseId = $this->input('source_warehouse_id');

            foreach ((array) $this->input('items', []) as $index => $item) {
                $listingId = $item['vendor_listing_id'] ?? null;
                $qty       = (int) ($item['quantity'] ?? 0);

                $listing = VendorListing::find($listingId);
                if ($listing && $listing->vendor_id !== $vendorId) {
                    $v->errors()->add('items', 'One or more products do not belong to your account.');
                    break;
                }

                if ($listing && $sourceWarehouseId) {
                    $inv = WarehouseInventory::where('warehouse_id', $sourceWarehouseId)
                        ->where('vendor_listing_id', $listingId)
                        ->first();

                    $available = $inv ? max(0, (int) $inv->quantity_available) : 0;

                    if ($qty > $available) {
                        $v->errors()->add(
                            "items.{$index}.quantity",
                            "Requested qty ({$qty}) exceeds available stock ({$available}) in source warehouse."
                        );
                    }

                    if ($available === 0) {
                        $v->errors()->add(
                            "items.{$index}.vendor_listing_id",
                            "This product has no available stock in the selected source warehouse."
                        );
                    }
                }
            }
        });
    }
}
