<?php

namespace App\Policies;

use App\Models\ReturnRequest;
use App\Models\VendorAdmin;

class ReturnRequestPolicy
{
    public function view(VendorAdmin $admin, ReturnRequest $return): bool
    {
        return $return->vendor_id === $admin->vendor_id;
    }

    public function addMessage(VendorAdmin $admin, ReturnRequest $return): bool
    {
        return $return->vendor_id === $admin->vendor_id;
    }
}
