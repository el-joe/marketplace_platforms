<?php

namespace App\Policies;

use App\Models\FlashSaleSubmission;
use App\Models\Vendor;

class FlashSaleSubmissionPolicy
{
    public function update(Vendor $vendor, FlashSaleSubmission $submission): bool
    {
        return $submission->vendor_id === $vendor->id;
    }

    public function delete(Vendor $vendor, FlashSaleSubmission $submission): bool
    {
        return $submission->vendor_id === $vendor->id;
    }
}
