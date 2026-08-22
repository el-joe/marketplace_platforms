<?php

namespace App\Policies;

use App\Models\SubOrder;
use App\Models\VendorAdmin;

class SubOrderPolicy
{
    public function view(VendorAdmin $admin, SubOrder $subOrder): bool
    {
        return $subOrder->vendor_id === $admin->vendor_id;
    }

    public function ship(VendorAdmin $admin, SubOrder $subOrder): bool
    {
        return $subOrder->vendor_id === $admin->vendor_id;
    }

    public function cancel(VendorAdmin $admin, SubOrder $subOrder): bool
    {
        return $subOrder->vendor_id === $admin->vendor_id;
    }

    public function packingSlip(VendorAdmin $admin, SubOrder $subOrder): bool
    {
        return $subOrder->vendor_id === $admin->vendor_id;
    }
}
