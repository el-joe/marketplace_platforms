<?php

namespace App\Policies;

use App\Models\DeliveryAgent;
use App\Models\ShippingCompanySupervisor;

class CarrierAgentPolicy
{
    /**
     * Returns false (not 403) whenever the agent is out of scope so the
     * controller can return a 404, never leaking cross-company existence.
     */
    private function inScope(ShippingCompanySupervisor $supervisor, DeliveryAgent $agent): bool
    {
        // Platform-employed agents (NULL shipping_company_id) are absolutely off-limits.
        if ($agent->shipping_company_id === null) {
            return false;
        }

        return $agent->shipping_company_id === $supervisor->shipping_company_id;
    }

    public function view(ShippingCompanySupervisor $supervisor, DeliveryAgent $agent): bool
    {
        return $this->inScope($supervisor, $agent);
    }

    public function update(ShippingCompanySupervisor $supervisor, DeliveryAgent $agent): bool
    {
        return $this->inScope($supervisor, $agent);
    }

    public function delete(ShippingCompanySupervisor $supervisor, DeliveryAgent $agent): bool
    {
        return $this->inScope($supervisor, $agent);
    }
}
