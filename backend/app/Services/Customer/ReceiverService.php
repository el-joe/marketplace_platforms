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
}
