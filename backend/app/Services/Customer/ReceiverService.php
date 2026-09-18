<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerReceiver;

class ReceiverService
{
    /**
     * Set the given receiver as default, unsetting any previous default
     * for this customer in a single UPDATE rather than N+1.
     */
    public function setDefault(Customer $customer, CustomerReceiver $receiver): void
    {
        $customer->receivers()
            ->where('id', '!=', $receiver->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $receiver->update(['is_default' => true]);
    }

    /**
     * Ensure a receiver exists for this customer with the given name/phone,
     * creating one (mirroring ReceiverController::store()'s default-assignment
     * rule) if no matching receiver is already on file. Used to keep
     * customer_receivers in sync whenever an address is created with a
     * recipient name/phone, without ever duplicating an existing receiver.
     */
    public function findOrCreateForNameAndPhone(Customer $customer, string $name, string $phone): CustomerReceiver
    {
        $existing = $customer->receivers()
            ->where('name', $name)
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return $existing;
        }

        $isFirst = ! $customer->receivers()->exists();

        if ($isFirst) {
            $customer->receivers()->where('is_default', true)->update(['is_default' => false]);
        }

        return $customer->receivers()->create([
            'name' => $name,
            'phone' => $phone,
            'is_default' => $isFirst,
        ]);
    }
}
