<?php

namespace App\Services\Customer;

use App\Exceptions\CustomerContractRequiredException;
use App\Jobs\NotifySellerNewInquiryJob;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Services\Marketer\ListingReferralService;

class ClassifiedInquiryService
{
    public function __construct(
        private readonly ListingReferralService $referrals,
        private readonly CustomerContractService $customerContracts,
    ) {}

    /**
     * @param  string|null  $sessionId  the visitor's session, used to credit the marketer whose link brought him here
     *
     * @throws CustomerContractRequiredException when the advertisement needs a customer contract the customer has not accepted
     */
    public function create(ClassifiedListing $listing, Customer $customer, array $data, ?string $sessionId = null): ClassifiedInquiry
    {
        $acceptance = $this->customerContracts->assertAccepted($listing, $customer);

        $inquiry = ClassifiedInquiry::create($this->referrals->attributes('classified', (string) $listing->id, $sessionId) + [
            'classified_listing_id' => $listing->id,
            'customer_id'           => $customer->id,
            'customer_contract_id'  => $acceptance?->id,
            'message'               => $data['message'],
            'contact_phone'         => $data['contact_phone'] ?? $customer->phone ?? null,
        ]);

        $this->customerContracts->linkInquiry($acceptance, $inquiry);

        NotifySellerNewInquiryJob::dispatch($inquiry);

        return $inquiry;
    }
}
