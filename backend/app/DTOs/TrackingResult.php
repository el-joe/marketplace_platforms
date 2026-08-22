<?php

namespace App\DTOs;

readonly class TrackingResult
{
    public function __construct(
        public bool $found,
        public string $trackingNumber,
        public string $currentStatus,
        /** Each: { status, description, location, occurred_at } */
        public array $events,
        public ?string $estimatedDelivery,
        public array $rawResponse,
    ) {
    }

    public static function notFound(string $trackingNumber, array $raw = []): self
    {
        return new self(
            found: false,
            trackingNumber: $trackingNumber,
            currentStatus: 'not_found',
            events: [],
            estimatedDelivery: null,
            rawResponse: $raw,
        );
    }

    public static function error(string $trackingNumber): self
    {
        return new self(
            found: false,
            trackingNumber: $trackingNumber,
            currentStatus: 'error',
            events: [],
            estimatedDelivery: null,
            rawResponse: [],
        );
    }
}
