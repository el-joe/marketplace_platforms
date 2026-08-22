<?php

namespace App\Policies;

use App\Models\VendorAdmin;
use App\Models\WarehouseInventory;

class WarehouseInventoryPolicy
{
    // Vendor can view their own stock wherever it lives (own warehouse or FBN)
    public function view(VendorAdmin $admin, WarehouseInventory $inventory): bool
    {
        return $inventory->vendorListing->vendor_id === $admin->vendor_id;
    }

    // Vendor can only adjust stock in their own warehouses — FBN stock is read-only
    public function adjust(VendorAdmin $admin, WarehouseInventory $inventory): bool
    {
        return $inventory->vendorListing->vendor_id === $admin->vendor_id
            && $inventory->warehouse->owner_vendor_id === $admin->vendor_id;
    }

    public function viewMovements(VendorAdmin $admin, WarehouseInventory $inventory): bool
    {
        return $inventory->vendorListing->vendor_id === $admin->vendor_id;
    }
}
