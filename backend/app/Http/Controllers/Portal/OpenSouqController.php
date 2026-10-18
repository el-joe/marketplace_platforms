<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\JoinService;
use Illuminate\View\View;

/**
 * Public Open Souq introduction landing reached from the /companies choice
 * screen. Mirrors the travel-agency and product-seller landings: it introduces
 * what can be listed across the classified categories and routes visitors into
 * a registration wizard.
 *
 * Until Open Souq has its own registration flow it hands off to the
 * product-seller (/vendors/register) wizard, matching the client's temporary
 * routing decision.
 */
class OpenSouqController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    public function landing(): View
    {
        $countries = $this->joinService->headerCountries();

        return view('portal.open-souq', [
            'countries' => $countries,
            'currentCountry' => $this->joinService->headerCountry($countries),
            'categories' => $this->joinService->classifiedCategories(),
        ]);
    }
}
