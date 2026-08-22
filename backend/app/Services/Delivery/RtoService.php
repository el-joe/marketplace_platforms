<?php

namespace App\Services\Delivery;

use App\Models\DeliveryAssignment;

class RtoService
{
    /**
     * Create a return-to-origin assignment from a failed delivery assignment.
     * Routes back to the vendor/warehouse — reuses the same shipment reference
     * so tracking history is continuous.
     */
    public function createReturnAssignment(DeliveryAssignment $failed): DeliveryAssignment
    {
        return DeliveryAssignment::create([
            'shipment_id'  => $failed->shipment_id,
            'sub_order_id' => $failed->sub_order_id,
            'agent_id'     => $failed->agent_id,
            'status'       => DeliveryAssignment::STATUS_ASSIGNED,
            'assigned_at'  => now(),
        ]);
    }
}
