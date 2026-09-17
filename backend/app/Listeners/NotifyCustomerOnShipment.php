<?php

namespace App\Listeners;

use App\Events\SubOrderShipped;
use App\Jobs\CustomerShippedNotificationJob;

/**
 * enhancement.md P-08 task 2: SubOrderShipped -> customer notification.
 * Inventory commit for the shipped path already happens in
 * Partner/OrderController::ship() (the single place that decrements
 * quantity_on_hand/quantity_reserved for a vendor shipment) — kept there
 * rather than duplicated here, per rule 0.1.3 (one source of truth).
 */
class NotifyCustomerOnShipment
{
    public function handle(SubOrderShipped $event): void
    {
        CustomerShippedNotificationJob::dispatch($event->subOrder->id);
    }
}
