<?php

namespace App\Services\Customer;

use App\Enums\TravelPackageStatus;
use App\Models\TravelPackage;
use Illuminate\Support\Carbon;

class TravelPackageDetailService
{
    public function findActive(string $slug): ?TravelPackage
    {
        return TravelPackage::where('slug', $slug)
            ->where('status', TravelPackageStatus::Active)
            ->where('departure_date', '>=', Carbon::today())
            ->with([
                'media'       => fn ($q) => $q->orderBy('position'),
                'categories:id,name_en,name_ar,slug',
                'inclusions:id,name_en,name_ar,icon',
                'agency:id,name,logo_path,license_number',
                'pricingTiers' => fn ($q) => $q->orderBy('travelers_count'),
            ])
            ->first();
    }
}
