<?php

namespace App\DTOs;

readonly class ShippingLabel
{
    public function __construct(
        public bool $success,
        public string $trackingNumber,
        public string $awbLabelUrl,
        public ?string $awbLabelBase64,
        public int $shippingCostCents,
        public string $currency,
        public ?string $carrierReferenceId,
        public ?string $errorMessage,
        public array $rawResponse,
    ) {
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(
            success: false,
            trackingNumber: '',
            awbLabelUrl: '',
            awbLabelBase64: null,
            shippingCostCents: 0,
            currency: '',
            carrierReferenceId: null,
            errorMessage: $message,
            rawResponse: $raw,
        );
    }
}
