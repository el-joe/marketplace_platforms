<?php

namespace App\Policies;

use App\Models\ShippingCompanySupervisor;

class SupervisorPolicy
{
    /**
     * All supervisor management actions are scoped to the same company.
     * The caller (auth supervisor) must belong to the same shipping_company_id
     * as the target supervisor.
     */
    private function sameCompany(ShippingCompanySupervisor $caller, ShippingCompanySupervisor $target): bool
    {
        return $caller->shipping_company_id === $target->shipping_company_id;
    }

    public function update(ShippingCompanySupervisor $caller, ShippingCompanySupervisor $target): bool
    {
        return $this->sameCompany($caller, $target);
    }

    public function delete(ShippingCompanySupervisor $caller, ShippingCompanySupervisor $target): bool
    {
        if (! $this->sameCompany($caller, $target)) {
            return false;
        }

        // A supervisor cannot soft-delete themselves.
        // // VERIFY: the schema has no is_owner / is_primary flag, so there is no
        // hard block on deleting the last/founding supervisor beyond self-deletion.
        // If an owner concept is added later, enforce it here.
        return $caller->id !== $target->id;
    }
}
