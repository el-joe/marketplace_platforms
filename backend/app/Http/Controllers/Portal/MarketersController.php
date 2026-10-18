<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\JoinService;
use Illuminate\View\View;

class MarketersController extends Controller
{
    public function __construct(private JoinService $joinService) {}

    /**
     * GET /marketers — the marketer section landing reached from /join.
     *
     * Instead of a choice form, each active marketer type is presented as its
     * own landing page and links straight into that page.
     */
    public function index(): View
    {
        $countries = $this->joinService->headerCountries();

        return view('portal.marketers', [
            'types' => $this->types(),
            'countries' => $countries,
            'currentCountry' => $this->joinService->headerCountry($countries),
        ]);
    }

    /**
     * GET /marketers/{marketerType} — the dedicated landing page for a single
     * marketer type, which hands off to the existing marketer registration flow.
     */
    public function show(string $marketerType): View
    {
        $labels = $this->joinService->marketerTypes();

        abort_unless(array_key_exists($marketerType, $labels), 404);

        $countries = $this->joinService->headerCountries();

        return view('portal.marketer-type', [
            'marketerType' => $marketerType,
            'typeLabel' => $labels[$marketerType],
            'countries' => $countries,
            'currentCountry' => $this->joinService->headerCountry($countries),
        ]);
    }

    /**
     * Active marketer types as landing-page cards, in the configured order.
     *
     * @return list<array{key: string, label: string, description: string, image: array{src: string, alt: string}}>
     */
    private function types(): array
    {
        $descriptions = [
            'influencer' => ['Promote products and listings to your audience with a fixed amount per campaign.', 'روّج للمنتجات والإعلانات لجمهورك مقابل مبلغ ثابت لكل حملة.'],
            'affiliate' => ['Earn a percentage of every sale you drive through your affiliate links.', 'اربح نسبة من كل عملية بيع تحققها عبر روابطك التسويقية.'],
            'broker' => ['Connect buyers and sellers and earn a fixed amount, a percentage, or both.', 'اربط المشترين بالبائعين واربح مبلغاً ثابتاً أو نسبة أو كليهما.'],
        ];

        $types = [];

        foreach ($this->joinService->marketerTypes() as $key => $label) {
            [$fallbackEn, $fallbackAr] = $descriptions[$key] ?? ['', ''];

            $types[] = [
                'key' => $key,
                'label' => $label,
                'description' => portal_content('marketers', 'card_'.$key, 'description', $fallbackEn, $fallbackAr),
                'image' => portal_image('marketers', 'card_'.$key, 'image', asset('images/join/marketer-type.jpg'), $label, $label),
            ];
        }

        return $types;
    }
}
