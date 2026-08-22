<?php

namespace App\Policies;

use App\Enums\ClassifiedListingStatus;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Vendor;
use App\Models\VendorAdmin;

class ClassifiedListingPolicy
{
    private function ownsListing(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $listing->seller_type === Vendor::class
            && $listing->seller_id === $admin->vendor_id;
    }

    public function view(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing);
    }

    public function update(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing)
            && in_array($listing->status, [ClassifiedListingStatus::Draft, ClassifiedListingStatus::Rejected], true);
    }

    public function pause(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing) && $listing->status === ClassifiedListingStatus::Active;
    }

    public function resume(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing) && $listing->status === ClassifiedListingStatus::Paused;
    }

    public function markSold(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing)
            && in_array($listing->status, [ClassifiedListingStatus::Active, ClassifiedListingStatus::Paused], true);
    }

    public function delete(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing)
            && in_array($listing->status, [ClassifiedListingStatus::Draft, ClassifiedListingStatus::Rejected, ClassifiedListingStatus::Expired], true);
    }

    public function viewContract(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing)
            && $listing->status === ClassifiedListingStatus::PendingContract;
    }

    public function acceptContract(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing)
            && $listing->status === ClassifiedListingStatus::PendingContract;
    }

    public function viewInquiries(VendorAdmin $admin, ClassifiedListing $listing): bool
    {
        return $this->ownsListing($admin, $listing);
    }
}
