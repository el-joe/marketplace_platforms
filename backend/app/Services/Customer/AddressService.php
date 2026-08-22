<?php

namespace App\Services\Customer;

use App\Models\Address;
use App\Models\Customer;

class AddressService
{
    /**
     * Set the given address as default, unsetting any previous default
     * for this customer in a single UPDATE rather than N+1.
     */
    public function setDefault(Customer $customer, Address $address): void
    {
        // Clear existing default in one query
        $customer->addresses()
            ->where('id', '!=', $address->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);
    }

    public function canDelete(Address $address): bool
    {
        // payment_methods table removed — saved cards no longer exist
        return true;
    }
}
