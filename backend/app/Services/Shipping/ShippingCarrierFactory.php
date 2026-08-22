<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingCarrierInterface;
use App\Services\Shipping\Carriers\AramexCarrier;
use App\Services\Shipping\Carriers\BostaCarrier;
use App\Services\Shipping\Carriers\DhlCarrier;
use App\Services\Shipping\Carriers\FedexCarrier;
use App\Services\Shipping\Carriers\ManualCarrier;

class ShippingCarrierFactory
{
    /** @var array<string, ShippingCarrierInterface> */
    private array $carriers = [];

    public function __construct()
    {
        $this->register(new AramexCarrier());
        $this->register(new BostaCarrier());
        $this->register(new DhlCarrier());
        $this->register(new FedexCarrier());
        $this->register(new ManualCarrier());
    }

    public function register(ShippingCarrierInterface $carrier): void
    {
        $this->carriers[$carrier->getCode()] = $carrier;
    }

    /**
     * Resolve a carrier by its code.
     * Falls back to ManualCarrier if code not implemented.
     */
    public function make(string $code): ShippingCarrierInterface
    {
        return $this->carriers[$code] ?? $this->carriers['manual'];
    }

    /** @return ShippingCarrierInterface[] */
    public function all(): array
    {
        return array_values($this->carriers);
    }

    /** @return string[] */
    public function allCodes(): array
    {
        return array_keys($this->carriers);
    }
}
