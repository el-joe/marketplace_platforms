<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\JoinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompaniesController extends Controller
{
    /**
     * The two ways a company/broker enters the classified side of the platform.
     *
     * @var list<string>
     */
    private const CHOICES = ['travel_agency', 'open_souq'];

    public function __construct(private JoinService $joinService) {}

    /**
     * GET /companies — pick how to join the classified side: as a travel agency
     * or through Open Souq, which reveals the classified categories to pick from.
     *
     * Mirrors the /join choice screen; the country is inherited from the portal
     * home page selection rather than being asked for again here.
     */
    public function index(): View
    {
        $countries = $this->joinService->headerCountries();
        $selection = (array) session('companies.selection', []);

        return view('portal.companies', [
            'cards' => $this->cards(),
            'categories' => $this->joinService->classifiedCategories(),
            'selection' => $selection,
            'countries' => $countries,
            'currentCountry' => $this->joinService->headerCountry($countries),
        ]);
    }

    /**
     * POST /companies/continue — validate the choice server-side and remember it.
     *
     * A travel-agency or Open Souq choice opens that section's introduction
     * landing page. Open Souq currently hands off to the product-seller
     * registration wizard from its landing until it has its own flow.
     */
    public function continue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', 'string', Rule::in(self::CHOICES)],
            'classified_category_id' => [
                'nullable',
                'string',
                Rule::exists('classified_categories', 'id')->where('is_active', true),
            ],
        ], [
            'choice.in' => __('portal.companies.choice_invalid'),
            'classified_category_id.exists' => __('portal.companies.category_unavailable'),
        ]);

        if ($validated['choice'] === 'open_souq' && empty($validated['classified_category_id'])) {
            throw ValidationException::withMessages([
                'classified_category_id' => __('portal.companies.category_required'),
            ]);
        }

        session(['companies.selection' => $validated]);

        if ($validated['choice'] === 'travel_agency') {
            return redirect()->route('portal.companies.travel-agency');
        }

        return redirect()->route('portal.companies.open-souq');
    }

    /**
     * Choice cards for the companies screen, CMS-editable via Portal Content.
     *
     * @return array<int, array{value: string, title: string, description: string, image: array{src: string, alt: string}}>
     */
    private function cards(): array
    {
        return [
            [
                'value' => 'travel_agency',
                'title' => portal_content('companies', 'card_travel_agency', 'title', 'Travel Agency', 'وكالة سفر'),
                'description' => portal_content('companies', 'card_travel_agency', 'description', 'Register your travel agency to publish trips, packages and bookings on Nawy.', 'سجّل وكالة السفر الخاصة بك لنشر الرحلات والباقات والحجوزات على ناوي.'),
                'image' => portal_image('companies', 'card_travel_agency', 'image', asset('images/join/travel-agency.jpg'), 'Travel agency', 'وكالة سفر'),
            ],
            [
                'value' => 'open_souq',
                'title' => portal_content('companies', 'card_open_souq', 'title', 'Open Souq', 'السوق المفتوح'),
                'description' => portal_content('companies', 'card_open_souq', 'description', 'List across our classified categories — real estate, cars, chalets and more.', 'أدرج إعلاناتك في فئات السوق المفتوح — العقارات والسيارات والشاليهات وغيرها.'),
                'image' => portal_image('companies', 'card_open_souq', 'image', asset('images/join/open-souq.jpg'), 'Open Souq', 'السوق المفتوح'),
            ],
        ];
    }
}
