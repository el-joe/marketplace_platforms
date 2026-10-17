<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\JoinService;
use Illuminate\View\View;

class CompaniesController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    /**
     * GET /companies — landing page for specialized companies and brokers.
     *
     * Specializations come from the existing classified_categories table; the
     * country is inherited from the portal home page selection rather than
     * being asked for again here.
     */
    public function index(): View
    {
        return view('portal.companies', [
            'categories' => $this->joinService->classifiedCategories(),
            'currentCountry' => $this->joinService->currentCountry(),
            'selection' => $this->joinService->currentSelection(),
        ]);
    }
}
