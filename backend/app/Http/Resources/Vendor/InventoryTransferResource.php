<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'transfer_number'          => $this->transfer_number,
            'status'                   => $this->status?->value,
            'source_warehouse_id'      => $this->source_warehouse_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'carrier'                  => $this->carrier,
            'tracking_number'          => $this->tracking_number,
            'expected_arrival_date'    => $this->expected_arrival_date?->toDateString(),
            'shipped_at'               => $this->shipped_at?->toIso8601String(),
            'received_at'              => $this->received_at?->toIso8601String(),
            'notes'                    => $this->notes,
            'source_warehouse'         => $this->whenLoaded('sourceWarehouse', fn () => [
                'id'   => $this->sourceWarehouse->id,
                'name' => $this->sourceWarehouse->name,
            ]),
            'destination_warehouse'    => $this->whenLoaded('destinationWarehouse', fn () => [
                'id'   => $this->destinationWarehouse->id,
                'name' => $this->destinationWarehouse->name,
                'type' => $this->destinationWarehouse->type?->value,
            ]),
            'items'                    => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'                 => $item->id,
                'vendor_listing_id'  => $item->vendor_listing_id,
                'quantity_requested' => (int) $item->quantity_requested,
                'quantity_received'  => (int) $item->quantity_received,
                'damaged_quantity'   => (int) $item->damaged_quantity,
                'condition_notes'    => $item->condition_notes,
            ])),
            'created_at'               => $this->created_at->toIso8601String(),
            'updated_at'               => $this->updated_at->toIso8601String(),
        ];
    }
}
