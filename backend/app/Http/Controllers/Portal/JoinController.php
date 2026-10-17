<?php

namespace App\Http\Controllers\Portal;

use App\Enums\JoinSection;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Portal\JoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JoinController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    /**
     * GET /join — the first screen: pick the account section only.
     *
     * No country, specialization or registration form is shown here; those
     * belong to each section's own journey. The cards are plain POST forms so
     * the choice is validated server-side before the visitor is routed on.
     */
    public function index(): View
    {
        $countries = $this->joinService->selectableCountries();
        $currentCountry = $this->joinService->currentCountry();

        return view('portal.join', [
            'sections' => $this->joinService->sections(),
            'selection' => $this->joinService->currentSelection(),
            'countries' => $countries,
            'currentCountry' => $currentCountry,
        ]);
    }

    /**
     * POST /join/select — validate the section choice server-side, remember it
     * for the rest of the journey, and hand off to that section's landing page.
     */
    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section' => ['required', 'string', Rule::in(JoinSection::values())],
        ], [
            'section.in' => __('portal.join.section_invalid'),
        ]);

        $this->joinService->storeSection($validated['section']);

        return redirect()->route($this->joinService->sectionLandingRoute($validated['section']));
    }

    /**
     * GET /join/options — section options for a chosen country (AJAX).
     *
     * Every option is re-read from the database here so the client can never
     * widen its own choices; the final decision is still re-validated in
     * {@see self::continue()} before any redirect happens.
     */
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'string', 'exists:countries,id'],
        ]);

        $country = Country::where('id', $validated['country_id'])
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'country_id' => $country->id,
            'classified_categories' => $this->joinService->classifiedCategories()->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'marketer_types' => $this->joinService->marketerTypes(),
        ]);
    }

    /**
     * POST /join/continue — validate the selection server-side and hand off to
     * the matching existing registration flow.
     */
    public function continue(Request $request): RedirectResponse
    {
        $validated = $this->joinService->validate($request->all());

        $this->joinService->storeSelection($validated);

        $destination = $this->joinService->resolve($validated);

        if ($destination['status'] === 'ready' && $destination['route'] !== null) {
            return redirect()->route($destination['route'], $destination['params']);
        }

        return redirect()
            ->route($this->joinService->sectionLandingRoute($validated['section']))
            ->with('join_status', $destination['message']);
    }
}
