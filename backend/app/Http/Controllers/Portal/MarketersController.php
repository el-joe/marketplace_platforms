<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\JoinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketersController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    /**
     * GET /marketers — pick the marketer type to register as, on the same
     * choice screen used by /join and /companies.
     *
     * The available types are read from the existing active marketer_jobs rows;
     * each one continues into the existing marketer registration flow.
     */
    public function index(): View
    {
        $countries = $this->joinService->headerCountries();
        $selection = (array) session('marketers.selection', []);

        return view('portal.marketers', [
            'cards' => $this->cards(),
            'selection' => $selection,
            'countries' => $countries,
            'currentCountry' => $this->joinService->headerCountry($countries),
        ]);
    }

    /**
     * POST /marketers/continue — validate the chosen type server-side and hand
     * off to the existing marketer registration flow.
     */
    public function continue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'marketer_type' => [
                'required',
                'string',
                Rule::in(array_keys($this->joinService->marketerTypes())),
            ],
        ], [
            'marketer_type.in' => __('portal.marketers.type_invalid'),
            'marketer_type.required' => __('portal.marketers.type_required'),
        ]);

        session(['marketers.selection' => $validated]);

        return redirect()->route('marketer.register', ['type' => $validated['marketer_type']]);
    }

    /**
     * Choice cards for the marketers screen, one per active marketer type.
     *
     * @return array<int, array{value: string, title: string, description: string, image: array{src: string, alt: string}}>
     */
    private function cards(): array
    {
        $descriptions = [
            'influencer' => ['Promote products and listings to your audience with a fixed amount per campaign.', 'روّج للمنتجات والإعلانات لجمهورك مقابل مبلغ ثابت لكل حملة.'],
            'affiliate' => ['Earn a percentage of every sale you drive through your affiliate links.', 'اربح نسبة من كل عملية بيع تحققها عبر روابطك التسويقية.'],
            'broker' => ['Connect buyers and sellers and earn a fixed amount, a percentage, or both.', 'اربط المشترين بالبائعين واربح مبلغاً ثابتاً أو نسبة أو كليهما.'],
        ];

        $cards = [];

        foreach ($this->joinService->marketerTypes() as $key => $label) {
            [$fallbackEn, $fallbackAr] = $descriptions[$key] ?? ['', ''];

            $cards[] = [
                'value' => $key,
                'title' => $label,
                'description' => portal_content('marketers', 'card_'.$key, 'description', $fallbackEn, $fallbackAr),
                'image' => portal_image('marketers', 'card_'.$key, 'image', asset('images/join/marketer-type.jpg'), $label, $label),
            ];
        }

        return $cards;
    }
}
