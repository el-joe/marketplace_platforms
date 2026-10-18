<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\TravelCategory;
use App\Services\Portal\JoinService;
use Illuminate\View\View;

/**
 * Public travel-agency introduction landing reached from the /companies choice
 * screen. Mirrors the product-seller landing: it introduces what a travel
 * agency can publish and routes visitors into the travel-agency registration
 * wizard.
 */
class TravelAgencyController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    public function landing(): View
    {
        $countries = $this->joinService->headerCountries();

        return view('portal.travel-agency', [
            'countries' => $countries,
            'currentCountry' => $this->joinService->headerCountry($countries),
            'categories' => TravelCategory::where('is_active', true)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get(),
        ]);
    }
}
