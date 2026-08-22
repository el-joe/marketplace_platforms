<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubOrderDetailResource extends JsonResource
{
    // PII reveal threshold: once status reaches processing or beyond,
    // the vendor needs the full address to fulfil the order.
    private const PII_HIDDEN_STATUSES = ['placed', 'confirmed'];

    public function toArray(Request $request): array
    {
        $address  = $this->order?->shipping_address_snapshot ?? [];
        $showFull = ! in_array($this->status, self::PII_HIDDEN_STATUSES);

        $customer = $this->buildCustomerPayload($address, $showFull);

        return [
            'sub_order_number'         => $this->sub_order_number,
            'order_id'                 => $this->order_id,
            'order_number'             => $this->order?->order_number,
            'payment_method'           => $this->order?->payment_method,
            'item_count'               => $this->items->count(),
            'status'                   => $this->status->value,
            'fulfillment_model'        => $this->fulfillment_model,
            'tracking_number'          => $this->tracking_number,
            'carrier_id'               => $this->carrier_id,
            'carrier_name'             => $this->carrier?->name,
            'currency'                 => $this->order?->currency,
            'cod_remittance_confirmed' => (bool) $this->cod_remittance_confirmed,
            'sla_ship_deadline'        => $this->sla_ship_deadline?->toIso8601String(),
            'sla_breached'             => (bool) $this->sla_breached,
            'estimated_delivery'       => $this->estimated_delivery_date?->toIso8601String(),
            'placed_at'                => $this->order?->placed_at?->toIso8601String(),
            'shipped_at'               => $this->shipped_at?->toIso8601String(),
            'delivered_at'             => $this->delivered_at?->toIso8601String(),
            'cancelled_at'             => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason'      => $this->cancellation_reason,

            'financials' => [
                'subtotal'             => (int) $this->subtotal,
                'shipping'             => (int) $this->shipping,
                'tax'                  => (int) $this->tax,
                'platform_commission'  => (int) $this->platform_commission,
                'gateway_fee'          => (int) $this->gateway_fee,
                'vendor_payout'        => (int) $this->vendor_payout,
            ],

            // Customer contact — PII masked until status >= processing
            'customer' => $customer,
            // Alias of customer.address — same PII-masking rules apply, never
            // the raw order.shipping_address_snapshot.
            'shipping_address' => $customer['address'],

            'items' => $this->items->map(fn ($item) => $this->buildItemPayload($item)),

            // Flattened across all shipments (usually one), latest first.
            'tracking_events' => $this->shipments
                ->flatMap(fn ($shipment) => $shipment->trackingEvents)
                ->sortByDesc('occurred_at')
                ->values()
                ->map(fn ($event) => [
                    'status'      => $event->status->value,
                    'description' => $event->description,
                    'location'    => $event->location,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ]),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function buildCustomerPayload(array $address, bool $showFull): array
    {
        $firstName = $address['first_name'] ?? '';
        $lastName  = $address['last_name']  ?? '';

        if ($showFull) {
            return [
                'name'    => trim("{$firstName} {$lastName}"),
                'phone'   => $address['phone']   ?? null,
                'address' => $address,
            ];
        }

        // Masked: first name + last initial, city only
        $lastInitial = $lastName ? strtoupper(substr($lastName, 0, 1)) . '.' : '';

        return [
            'name'    => trim("{$firstName} {$lastInitial}"),
            'phone'   => null,
            'address' => [
                'city'    => $address['city']    ?? null,
                'country' => $address['country'] ?? null,
            ],
        ];
    }

    private function buildItemPayload($item): array
    {
        // Snapshot is the source of truth — never re-derive from live product data
        $snapshot = $item->product_snapshot ?? [];

        return [
            'id'                    => $item->id,
            'sku'                   => $item->sku,
            'listing_ref'           => $snapshot['listing_ref'] ?? null,
            'name_en'               => $snapshot['name_en']     ?? null,
            'name_ar'               => $snapshot['name_ar']     ?? null,
            'thumbnail'             => $snapshot['thumbnail_url'] ?? null,
            'quantity'              => $item->quantity,
            'unit_price'      => (int) $item->unit_price,
            'line_total'      => (int) $item->line_total,
            'fulfillment_status'    => $item->fulfillment_status->value,
            'return_eligible_until' => $item->return_eligible_until?->toIso8601String(),
        ];
    }
}
