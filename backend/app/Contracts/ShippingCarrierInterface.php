<?php

namespace App\Contracts;

use App\DTOs\ShippingLabel;
use App\DTOs\TrackingResult;
use App\Models\Shipment;
use App\Models\SubOrder;

interface ShippingCarrierInterface
{
    /** Matches shipping_carriers.code */
    public function getCode(): string;

    public function getName(): string;

    /** Create shipment and get AWB label */
    public function createShipment(SubOrder $subOrder, array $options = []): ShippingLabel;

    /** Get tracking events from carrier API */
    public function getTracking(string $trackingNumber): TrackingResult;

    /** Cancel/return a shipment */
    public function cancelShipment(Shipment $shipment): bool;

    /**
     * Calculate shipping cost.
     * @param  string  $currency  ISO 4217 currency code for the origin country (e.g. 'SAR', 'AED', 'EGP')
     * Returns: { rate: int, currency: string, estimated_days: int, service_name: string }
     */
    public function calculateRate(
        array $fromAddress,
        array $toAddress,
        int $weightGrams,
        array $dimensions = [],
        string $currency = '',
    ): array;

    /**
     * Test carrier API connection.
     * Returns: { success: bool, latency_ms: int, message: string }
     */
    public function testConnection(): array;

    /** Does carrier support COD */
    public function supportsCod(): bool;
}
