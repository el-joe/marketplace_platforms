<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\Shipment;
use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'sub_orders' => $this->subOrders->mapWithKeys(fn (SubOrder $subOrder) => [
                $subOrder->sub_order_number => [
                    'status' => $subOrder->status?->value,
                    'carrier' => $subOrder->carrier?->name,
                    'tracking_number' => $subOrder->tracking_number,
                    'estimated_delivery_date' => $subOrder->estimated_delivery_date?->toDateString(),
                    'shipped_at' => $subOrder->shipped_at?->toIso8601String(),
                    'delivered_at' => $subOrder->delivered_at?->toIso8601String(),
                    'shipments' => $subOrder->shipments->map(fn (Shipment $shipment) => [
                        'carrier_id' => $shipment->carrier_id,
                        'tracking_number' => $shipment->tracking_number,
                        'awb_label_url' => $shipment->awb_label_url,
                        'status' => $shipment->status?->value,
                        'picked_up_at' => $shipment->picked_up_at?->toIso8601String(),
                        'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                        'events' => $shipment->trackingEvents
                            ->sortBy('occurred_at')
                            ->values()
                            ->map(fn ($event) => [
                                'status' => $event->status?->value,
                                'description' => $event->description,
                                'location' => $event->location,
                                'occurred_at' => $event->occurred_at?->toIso8601String(),
                            ]),
                    ]),
                ],
            ]),
        ];
    }
}
