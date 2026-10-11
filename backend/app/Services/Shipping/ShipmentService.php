<?php

namespace App\Services\Shipping;

use App\Enums\CancelActor;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingCompanySupervisor;
use App\Models\SubOrder;
use App\Notifications\Carrier\NewUnassignedShipmentArrived;
use App\Services\OrderStateMachine;
use Illuminate\Support\Facades\Notification;

class ShipmentService
{
    public function __construct(private readonly OrderStateMachine $stateMachine)
    {
    }

    /**
     * Move the sub-order to `shipped` through the state machine, create its
     * Shipment and notify supervisors of the new unassigned shipment.
     * Caller wraps this in a DB transaction.
     *
     * $advance walks confirmed -> processing -> packed first (admin use).
     */
    public function createForSubOrder(
        SubOrder $subOrder,
        string $trackingNumber,
        CancelActor $actor,
        array $meta = [],
        bool $advance = false,
    ): Shipment {
        if ($advance) {
            $steps   = ['confirmed', 'processing', 'packed'];
            $current = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;
            $index   = array_search($current, $steps, true);

            foreach (array_slice($steps, $index === false ? 0 : $index + 1) as $step) {
                $this->stateMachine->transition($subOrder, $step, $actor, ['action' => $step]);
            }
        }

        $this->stateMachine->transition(
            $subOrder,
            'shipped',
            $actor,
            array_merge($meta, [
                'tracking_number' => $trackingNumber,
                'carrier_id' => $subOrder->carrier_id,
                'action' => 'shipped',
            ]),
        );

        $variantIds = $subOrder->items->pluck('product_variant_id');
        $variantWeights = ProductVariant::whereIn('id', $variantIds)->pluck('weight_grams', 'id');
        $weightGrams = $subOrder->items->sum(
            fn ($item) => ($variantWeights[$item->product_variant_id] ?? 0) * $item->quantity
        );

        $shipment = Shipment::create([
            'sub_order_id' => $subOrder->id,
            'carrier_id' => $subOrder->carrier_id,
            'tracking_number' => $trackingNumber,
            'weight_grams' => $weightGrams,
            'shipping_cost_actual' => $subOrder->shipping,
            'status' => 'label_created',
        ]);

        // No shipping_company_id on shipments yet, so any company's
        // supervisors may claim this shipment.
        $supervisors = ShippingCompanySupervisor::receivingNotifications()->get();
        if ($supervisors->isNotEmpty()) {
            Notification::send($supervisors, new NewUnassignedShipmentArrived($shipment));
        }

        return $shipment;
    }
}
