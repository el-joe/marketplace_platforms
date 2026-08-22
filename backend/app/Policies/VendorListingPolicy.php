<?php

namespace App\Policies;

use App\Models\VendorAdmin;
use App\Models\VendorListing;

class VendorListingPolicy
{
    public function view(VendorAdmin $admin, VendorListing $listing): bool
    {
        return $listing->vendor_id === $admin->vendor_id;
    }

    public function updatePrice(VendorAdmin $admin, VendorListing $listing): bool
    {
        return $listing->vendor_id === $admin->vendor_id;
    }

    public function updateStatus(VendorAdmin $admin, VendorListing $listing): bool
    {
        return $listing->vendor_id === $admin->vendor_id;
    }

    public function updateShipping(VendorAdmin $admin, VendorListing $listing): bool
    {
        return $listing->vendor_id === $admin->vendor_id;
    }

    public function updateDeliveryCoverage(VendorAdmin $admin, VendorListing $listing): bool
    {
        return $listing->vendor_id === $admin->vendor_id;
    }

    public function delete(VendorAdmin $admin, VendorListing $listing): bool
    {
        return $listing->vendor_id === $admin->vendor_id;
    }
}
