<?php

namespace App\Services\Customer;

use App\Jobs\NotifySellerNewInquiryJob;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Services\Marketer\ListingReferralService;

class ClassifiedInquiryService
{
    public function __construct(private readonly ListingReferralService $referrals) {}

    /**
     * @param  string|null  $sessionId  the visitor's session, used to credit the marketer whose link brought him here
     */
    public function create(ClassifiedListing $listing, Customer $customer, array $data, ?string $sessionId = null): ClassifiedInquiry
    {
        $inquiry = ClassifiedInquiry::create($this->referrals->attributes('classified', (string) $listing->id, $sessionId) + [
            'classified_listing_id' => $listing->id,
            'customer_id'           => $customer->id,
            'message'               => $data['message'],
            'contact_phone'         => $data['contact_phone'] ?? $customer->phone ?? null,
        ]);

        NotifySellerNewInquiryJob::dispatch($inquiry);

        return $inquiry;
    }
}
