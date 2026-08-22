<?php

namespace App\Services\Customer;

use App\Jobs\NotifySellerNewInquiryJob;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Customer;

class ClassifiedInquiryService
{
    public function create(ClassifiedListing $listing, Customer $customer, array $data): ClassifiedInquiry
    {
        $inquiry = ClassifiedInquiry::create([
            'classified_listing_id' => $listing->id,
            'customer_id'           => $customer->id,
            'message'               => $data['message'],
            'contact_phone'         => $data['contact_phone'] ?? $customer->phone ?? null,
        ]);

        NotifySellerNewInquiryJob::dispatch($inquiry);

        return $inquiry;
    }
}
