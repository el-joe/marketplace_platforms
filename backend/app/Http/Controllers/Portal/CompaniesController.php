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
     * Specializations come from the existing classified_categories table and
     * countries from the existing countries table; no new settings are added.
     */
    public function index(): View
    {
        return view('portal.companies', [
            'categories' => $this->joinService->classifiedCategories(),
            'countries' => $this->joinService->countries(),
            'selection' => $this->joinService->currentSelection(),
        ]);
    }
}
