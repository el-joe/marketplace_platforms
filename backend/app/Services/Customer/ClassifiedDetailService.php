<?php

namespace App\Services\Customer;

use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Vendor;

class ClassifiedDetailService
{
    public function findActive(string $slug, Country $country): ?ClassifiedListing
    {
        return ClassifiedListing::where('slug', $slug)
            ->where('status', 'active')
            ->where('country_id', $country->id)
            ->with([
                'images'           => fn ($q) => $q->orderBy('position'),
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

    public function sellerInfo(ClassifiedListing $listing): array
    {
        if ($listing->seller_type === Vendor::class) {
            /** @var \App\Models\Vendor $vendor */
            $vendor = $listing->seller;

            return [
                'type'         => 'vendor',
                'display_name' => $vendor?->store_name ?? 'Vendor',
                'store_url'    => null, // extend when vendor public store pages exist
            ];
        }

        /** @var \App\Models\Customer $customer */
        $customer = $listing->seller;
        $parts       = preg_split('/\s+/', trim($customer?->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $firstName   = $parts[0] ?? 'Individual';
        $lastInitial = isset($parts[1]) ? mb_substr($parts[1], 0, 1) . '.' : '';

        return [
            'type'         => 'individual',
            'display_name' => trim($firstName . ' ' . $lastInitial),
            'store_url'    => null,
        ];
    }
}
