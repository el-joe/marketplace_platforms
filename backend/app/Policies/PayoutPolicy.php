<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\Vendor;

class PayoutPolicy
{
    public function view(Vendor $vendor, Payout $payout): bool
    {
        return $payout->vendor_id === $vendor->id;
    }
}
