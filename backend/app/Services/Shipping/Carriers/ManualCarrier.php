<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ShippingCarrierInterface;
use App\DTOs\ShippingLabel;
use App\DTOs\TrackingResult;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Models\SubOrder;
use Illuminate\Support\Str;

/**
 * Manual carrier — no API calls.
 * Admin manually enters tracking numbers.
 * Acts as the fallback / null-object carrier.
 */
class ManualCarrier implements ShippingCarrierInterface
{
    public function getCode(): string
    {
        return 'manual';
    }
    public function getName(): string
    {
        return 'Manual Fulfillment';
    }
    public function supportsCod(): bool
    {
        return true;
    }

    public function createShipment(SubOrder $subOrder, array $options = []): ShippingLabel
    {
        // Generate a placeholder tracking number until admin enters a real one
        $placeholder = 'MANUAL-' . strtoupper(Str::random(8));

        return new ShippingLabel(
            success: true,
            trackingNumber: $placeholder,
            awbLabelUrl: '',
            awbLabelBase64: null,
            shippingCost: 0,
            currency: '',
            carrierReferenceId: null,
            errorMessage: null,
            rawResponse: ['note' => 'Manual shipment — tracking must be updated by admin'],
        );
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        // Look up the carrier's tracking URL pattern from DB
        $carrier = ShippingCarrier::where('code', 'manual')->first();
        $trackingUrl = $carrier?->tracking_url_pattern
            ? str_replace('{tracking_number}', $trackingNumber, $carrier->tracking_url_pattern)
            : '';

        return new TrackingResult(
            found: true,
            trackingNumber: $trackingNumber,
            currentStatus: 'manual',
            events: [
                [
                    'status' => 'manual',
                    'description' => 'Tracking must be checked manually.',
                    'location' => '',
                    'occurred_at' => now()->toIso8601String(),
                ]
            ],
            estimatedDelivery: null,
            rawResponse: ['tracking_url' => $trackingUrl],
        );
    }

    public function cancelShipment(Shipment $shipment): bool
    {
        return true; // No API to call — just mark cancelled in the system
    }

    public function calculateRate(array $from, array $to, int $weightGrams, array $dimensions = [], string $currency = ''): array
    {
        return [
            'rate' => 0,
            'currency' => $currency,
            'estimated_days' => 5,
            'service_name' => 'Manual Fulfillment',
        ];
    }

    public function testConnection(): array
    {
        return [
            'success' => true,
            'latency_ms' => 0,
            'message' => 'Manual carrier requires no external connection.',
        ];
    }
}
