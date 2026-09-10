<?php

namespace App\Services\Ads;

use App\Enums\PaidAdAdvertiserType;
use App\Enums\PaidAdDestinationType;
use App\Models\Category;
use App\Models\ClassifiedListing;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerProfile;
use App\Models\PaidAdBooking;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Customer\ListingIdentifierService;
use DomainException;

class AdDestinationResolver
{
    public function __construct(private readonly ListingIdentifierService $listingIdentifierService)
    {
    }

    /**
     * Never accepts a URL from vendor/marketer input — destination URLs are always built here.
     *
     * @return array{destination_type: string, destination_reference_id: ?string, destination_url: string, referral_code: ?string, is_external: bool}
     */
    public function resolve(PaidAdBooking $booking, string $type, ?string $referenceId, ?string $externalUrl = null): array
    {
        $destinationType = $type instanceof PaidAdDestinationType ? $type : PaidAdDestinationType::from($type);

        return match ($destinationType) {
            PaidAdDestinationType::Listing => $this->resolveListing($booking, $referenceId),
            PaidAdDestinationType::ClassifiedListing => $this->resolveClassifiedListing($booking, $referenceId),
            PaidAdDestinationType::Store => $this->resolveStore($booking),
            PaidAdDestinationType::Brand => $this->resolveBrand($booking, $referenceId),
            PaidAdDestinationType::Category => $this->resolveCategory($booking, $referenceId),
            PaidAdDestinationType::MarketerProfile => $this->resolveMarketerProfile($booking),
            PaidAdDestinationType::Campaign => $this->resolveCampaign($booking, $referenceId),
            PaidAdDestinationType::External => $this->resolveExternal($booking, $externalUrl),
        };
    }

    private function resolveListing(PaidAdBooking $booking, ?string $referenceId): array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Vendor || ! $referenceId) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $listing = VendorListing::where('id', $referenceId)
            ->where('vendor_id', $booking->vendor_id)
            ->where('status', 'active')
            ->first();

        if (! $listing) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        // VERIFY: mirrors ReferralTrackingController's redirect target, which uses the raw
        // vendor_listing id as the /products/{slug} path segment (opaque identifier resolved
        // server-side by ListingIdentifierService::detectType()).
        $listingRef = $this->listingIdentifierService->buildListingRef($listing);

        return [
            'destination_type' => PaidAdDestinationType::Listing->value,
            'destination_reference_id' => $listing->id,
            'destination_url' => "/products/{$listingRef}",
            'referral_code' => null,
            'is_external' => false,
        ];
    }

    private function resolveClassifiedListing(PaidAdBooking $booking, ?string $referenceId): array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Vendor || ! $referenceId) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $listing = ClassifiedListing::where('id', $referenceId)
            ->where('seller_type', Vendor::class)
            ->where('seller_id', $booking->vendor_id)
            ->where('status', 'active')
            ->first();

        if (! $listing) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        // VERIFY: matches frontend route classified/find/[slug] which resolves classified_listings.slug.
        return [
            'destination_type' => PaidAdDestinationType::ClassifiedListing->value,
            'destination_reference_id' => $listing->id,
            'destination_url' => "/classified/find/{$listing->slug}",
            'referral_code' => null,
            'is_external' => false,
        ];
    }

    private function resolveStore(PaidAdBooking $booking): array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Vendor || ! $booking->vendor_id) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        return [
            'destination_type' => PaidAdDestinationType::Store->value,
            'destination_reference_id' => $booking->vendor_id,
            'destination_url' => "/seller/{$booking->vendor_id}",
            'referral_code' => null,
            'is_external' => false,
        ];
    }

    private function resolveBrand(PaidAdBooking $booking, ?string $referenceId): array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Vendor || ! $referenceId) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $hasActiveListing = VendorListing::where('vendor_id', $booking->vendor_id)
            ->where('status', 'active')
            ->whereHas('productVariant.product', fn ($q) => $q->where('brand_id', $referenceId))
            ->exists();

        if (! $hasActiveListing) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        // VERIFY: BrandPageController resolves brands by raw Brand::id (route customer.brands.show).
        return [
            'destination_type' => PaidAdDestinationType::Brand->value,
            'destination_reference_id' => $referenceId,
            'destination_url' => "/brands/{$referenceId}",
            'referral_code' => null,
            'is_external' => false,
        ];
    }

    private function resolveCategory(PaidAdBooking $booking, ?string $referenceId): array
    {
        if (! in_array($booking->advertiser_type, [PaidAdAdvertiserType::Vendor], true) && $booking->created_by_admin_id === null) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $category = Category::where('id', $referenceId)
            ->where('is_active', true)
            ->first();

        if (! $category || $category->country_id !== $booking->country_id) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        // VERIFY: matches the polymorphic slugs table used by the [...categorySlug] catch-all route.
        return [
            'destination_type' => PaidAdDestinationType::Category->value,
            'destination_reference_id' => $category->id,
            'destination_url' => "/{$category->slug}",
            'referral_code' => null,
            'is_external' => false,
        ];
    }

    private function resolveMarketerProfile(PaidAdBooking $booking): array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Marketer || ! $booking->marketer_id) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $profile = MarketerProfile::where('marketer_id', $booking->marketer_id)
            ->whereNotNull('profile_slug')
            ->first();

        if (! $profile) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        return [
            'destination_type' => PaidAdDestinationType::MarketerProfile->value,
            'destination_reference_id' => $profile->id,
            'destination_url' => "/marketer/{$profile->profile_slug}",
            'referral_code' => null,
            'is_external' => false,
        ];
    }

    private function resolveCampaign(PaidAdBooking $booking, ?string $referenceId): array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Marketer || ! $referenceId) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $invitation = MarketerCampaignInvitation::where('campaign_id', $referenceId)
            ->where('marketer_id', $booking->marketer_id)
            ->where('status', 'accepted')
            ->first();

        if (! $invitation || ! $invitation->referral_code) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        $campaign = MarketerCampaign::find($referenceId);
        if (! $campaign || ! in_array($campaign->status, ['active', 'auto_approved'], true)) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        // VERIFY: marketer_campaigns has no starts_at/ends_at columns in the current schema,
        // so there is no campaign date window to validate the booking dates against here.

        $url = rtrim(config('app.url'), '/').'/r/'.$invitation->referral_code;

        return [
            'destination_type' => PaidAdDestinationType::Campaign->value,
            'destination_reference_id' => $campaign->id,
            'destination_url' => $url,
            'referral_code' => $invitation->referral_code,
            'is_external' => true,
        ];
    }

    private function resolveExternal(PaidAdBooking $booking, ?string $externalUrl): array
    {
        if ($booking->created_by_admin_id === null || ! $externalUrl) {
            throw new DomainException(__('ads.errors.destination_invalid'));
        }

        return [
            'destination_type' => PaidAdDestinationType::External->value,
            'destination_reference_id' => null,
            'destination_url' => $externalUrl,
            'referral_code' => null,
            'is_external' => true,
        ];
    }
}
