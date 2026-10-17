<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\JoinService;
use Illuminate\View\View;

class MarketersController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    /**
     * GET /marketers — landing page for influencers, creators and affiliates.
     *
     * The available types are read from the existing active marketer_jobs rows;
     * each one links into the existing marketer registration flow.
     */
    public function index(): View
    {
        return view('portal.marketers', [
            'marketerTypes' => $this->joinService->marketerTypes(),
            'selection' => $this->joinService->currentSelection(),
        ]);
    }
}
