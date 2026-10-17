<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Portal\JoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JoinController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    /**
     * GET /join — the account-type landing page.
     */
    public function index(Request $request): View
    {
        return view('portal.join', [
            'countries' => $this->joinService->countries(),
            'sections' => $this->joinService->sections(),
            'classifiedCategories' => $this->joinService->classifiedCategories(),
            'marketerTypes' => $this->joinService->marketerTypes(),
            'selection' => $this->joinService->currentSelection(),
        ]);
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
            ->route('portal.join')
            ->with('join_status', $destination['message']);
    }
}
