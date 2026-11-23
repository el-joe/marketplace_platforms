<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
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
        return $this->render(null);
    }

    /**
     * GET /companies/open-souq/{category} — the landing of one classified section (real estate, cars, ...),
     * reached from its sub-option on /join. Its registration link carries the category so the wizard shows and
     * collects that section's contract. Unknown, inactive or sub-categories are not found.
     */
    public function category(string $category): View
    {
        $countries = $this->joinService->headerCountries();
        $selected = $this->joinService->findJoinableClassifiedCategory($category, $this->joinService->headerCountry($countries));

        abort_unless($selected !== null, 404);

        return $this->render($selected);
    }

    private function render(?ClassifiedCategory $selectedCategory): View
    {
        $countries = $this->joinService->headerCountries();
        $currentCountry = $this->joinService->headerCountry($countries);

        return view('portal.open-souq', [
            'countries' => $countries,
            'currentCountry' => $currentCountry,
            'categories' => $this->joinService->joinableClassifiedCategories($currentCountry),
            'selectedCategory' => $selectedCategory,
            'travelFolded' => $this->joinService->travelFoldedIntoClassifieds(),
        ]);
    }
}
