<?php

namespace App\Services\Customer;

use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Customer;
use App\Models\ExclusiveContract;
use App\Models\Vendor;

class ClassifiedDetailService
{
    public function findActive(string $slug, Country $country): ?ClassifiedListing
    {
        return ClassifiedListing::where(function ($q) use ($slug) {
            $q->where('slug', $slug)
                ->orWhere('listing_number', $slug);
        })
            ->where('status', 'active')
            ->where('country_id', $country->id)
            ->with([
            'images' => fn ($q) => $q->orderBy('position'),
            'city',
            'seller',
            'classifiedCategory:id,name_en,name_ar',
        ])
            ->first();
    }

    public function incrementViews(ClassifiedListing $listing): void
    {
        $listing->increment('views_count');
    }

    /**
     * Return the currently active exclusive contract covering this listing,
     * checking a listing-specific contract first, then falling back to a
     * category-wide contract for the listing's classified category.
     */
    public function activeExclusiveContract(ClassifiedListing $listing): ?ExclusiveContract
    {
        $listingContract = ExclusiveContract::where('classified_listing_id', $listing->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with('marketer')
            ->latest('starts_at')
            ->first();

        if ($listingContract) {
            return $listingContract;
        }

        return ExclusiveContract::whereNull('classified_listing_id')
            ->where('classified_category_id', $listing->classified_category_id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with('marketer')
            ->latest('starts_at')
            ->first();
    }

    public function sellerInfo(ClassifiedListing $listing): array
    {
        if ($listing->seller_type === Vendor::class) {
            /** @var Vendor $vendor */
            $vendor = $listing->seller;

            $listingCount = ClassifiedListing::where('seller_type', Vendor::class)
                ->where('seller_id', $vendor?->id)
                ->where('status', 'active')
                ->count();

            return [
                'id' => $vendor?->id,
                'type' => 'vendor',
                'display_name' => $vendor?->store_name ?? 'Vendor',
                'positive_rating' => $vendor?->positive_rating_pct,
                'member_since' => $vendor?->created_at?->format('Y'),
                'years_active' => $vendor?->partner_years,
                'active_listings' => $listingCount,
                'store_url' => null, // extend when vendor public store pages exist
            ];
        }

        /** @var Customer $customer */
        $customer = $listing->seller;
        $parts = preg_split('/\s+/', trim($customer?->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $firstName = $parts[0] ?? 'Individual';
        $lastInitial = isset($parts[1]) ? mb_substr($parts[1], 0, 1).'.' : '';

        $listingCount = ClassifiedListing::where('seller_type', Customer::class)
            ->where('seller_id', $customer?->id)
            ->where('status', 'active')
            ->count();

        return [
            'id' => null,
            'type' => 'individual',
            'display_name' => trim($firstName.' '.$lastInitial),
            'positive_rating' => null,
            'member_since' => $customer?->created_at?->format('Y'),
            'years_active' => $customer?->created_at ? (int) $customer->created_at->diffInYears(now()) : null,
            'active_listings' => $listingCount,
            'store_url' => null,
        ];
    }
}
