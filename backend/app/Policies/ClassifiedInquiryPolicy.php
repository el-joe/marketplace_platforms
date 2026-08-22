<?php

namespace App\Policies;

use App\Models\ClassifiedInquiry;
use App\Models\Vendor;
use App\Models\VendorAdmin;

class ClassifiedInquiryPolicy
{
    public function updateStatus(VendorAdmin $admin, ClassifiedInquiry $inquiry): bool
    {
        $listing = $inquiry->listing;

        return $listing->seller_type === Vendor::class
            && $listing->seller_id === $admin->vendor_id;
    }
}
