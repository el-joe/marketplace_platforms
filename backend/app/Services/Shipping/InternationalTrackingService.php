<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use Carbon\Carbon;

/**
 * docs/plans/international_product_shipping.md Phase 4, design decision #6.
 *
 * A thin, append-only helper for recording one leg of a (possibly
 * multi-leg / cross-border) shipment's tracking trail. It intentionally
 * does nothing beyond insert a new shipment_tracking_events row —
 * shipment_tracking_events is append-only by convention (never update an
 * existing row) and shipments.status stays the coarse top-level state.
 */
class InternationalTrackingService
{
    public function recordLeg(
        Shipment $shipment,
        string $status,
        string $description,
        ?string $carrierId,
        ?string $externalTrackingNumber,
        ?string $location,
        Carbon $occurredAt,
    ): ShipmentTrackingEvent {
        return $shipment->trackingEvents()->create([
            'status' => $status,
            'description' => $description,
            'carrier_id' => $carrierId,
            'external_tracking_number' => $externalTrackingNumber,
            'location' => $location,
            'occurred_at' => $occurredAt,
        ]);
    }
}
