<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\Customer;

class AddressPolicy
{
    private function owns(Customer $customer, Address $address): bool
    {
        return $address->addressable_type === Customer::class
            && $address->addressable_id === $customer->id;
    }

    public function view(Customer $customer, Address $address): bool
    {
        return $this->owns($customer, $address);
    }

    public function update(Customer $customer, Address $address): bool
    {
        return $this->owns($customer, $address);
    }

    public function delete(Customer $customer, Address $address): bool
    {
        return $this->owns($customer, $address);
    }

    public function setDefault(Customer $customer, Address $address): bool
    {
        return $this->owns($customer, $address);
    }
}
