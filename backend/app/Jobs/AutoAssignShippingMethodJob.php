<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\OrderStatusHistory;
use App\Models\ShippingRate;
use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AutoAssignShippingMethodJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public string $subOrderId)
    {
    }

    public function handle(): void
    {
        $subOrder = SubOrder::find($this->subOrderId);

        if (!$subOrder || $subOrder->shipping_method_id !== null) {
            return;
        }

        if (in_array($subOrder->status->value, ['shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'returned', 'refunded'])) {
            return;
        }

        $snapshot = $subOrder->order->shipping_address_snapshot ?? [];
        $cityId = $snapshot['city_id'] ?? null;
        $destinationZoneId = $cityId ? City::find($cityId)?->shipping_zone_id : null;

        if (!$destinationZoneId) {
            Log::warning('AutoAssignShippingMethodJob: cannot resolve zone', [
                'sub_order_id' => $subOrder->id,
                'city_id' => $cityId,
            ]);
            return;
        }

        $isCod = $subOrder->order->payment_method === 'cod';

        $query = ShippingRate::where('destination_zone_id', $destinationZoneId)
            ->where('is_active', true)
            ->whereHas('shippingMethod', fn($q) => $q->where('is_active', true))
            ->with(['shippingMethod', 'carrier']);

        if ($isCod) {
            $query->whereHas('carrier', fn($q) => $q->where('supports_cod', true));
        }

        $cheapestRate = $query->orderBy('base_fee')->first();

        if (!$cheapestRate) {
            Log::warning('AutoAssignShippingMethodJob: no eligible rate found', [
                'sub_order_id' => $subOrder->id,
                'destination_zone_id' => $destinationZoneId,
                'payment_method' => $subOrder->order->payment_method,
            ]);
            return;
        }

        $fromStatus = $subOrder->status->value;

        $subOrder->update([
            'shipping_method_id' => $cheapestRate->shipping_method_id,
            'carrier_id' => $cheapestRate->carrier_id,
        ]);

        OrderStatusHistory::create([
            'order_id' => $subOrder->order_id,
            'sub_order_id' => $subOrder->id,
            'from_status' => $fromStatus,
            'to_status' => $fromStatus,
            'changed_by_admin_id' => null,
            'reason' => 'Shipping method auto-assigned after 12-hour window expired.',
            'metadata' => json_encode([
                'action' => 'shipping_method_auto_assigned',
                'shipping_method_id' => $cheapestRate->shipping_method_id,
                'carrier_id' => $cheapestRate->carrier_id,
                'rate_id' => $cheapestRate->id,
                'auto_assigned_at' => now(),
            ]),
        ]);
    }
}
