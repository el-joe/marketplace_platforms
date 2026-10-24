<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PortalContentType;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PortalContent;
use App\Services\PortalContentCountrySync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PortalContentController extends Controller
{
    public function __construct(private readonly PortalContentCountrySync $sync) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Index — pages × active countries
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $this->authorizeView();

        $config = config('portal_sections', []);
        $order = array_flip(array_keys($config));

        $pages = PortalContent::query()
            ->where('country_code', '*')
            ->selectRaw('page_key, COUNT(*) as fields_count, MAX(updated_at) as last_updated_at')
            ->groupBy('page_key')
            ->get()
            ->sortBy(fn ($p) => sprintf('%05d-%s', $order[$p->page_key] ?? 99999, $p->page_key))
            ->values();

        return view('admin.portal-content.index', [
            'pages' => $pages,
            'countries' => $this->activeCountries(),
            'config' => $config,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => __('admin.portal_content.title')],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Page — sections (in portal order) for one country + page
    // ─────────────────────────────────────────────────────────────────────────

    public function page(string $country, string $pageKey): View
    {
        $this->authorizeView();

        $countryModel = $this->resolveCountry($country);
        $this->assertKnownPage($pageKey);

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $code = strtolower($countryModel->site_code);
        $declared = $this->declaredBands($pageKey);

        foreach (array_unique(array_column($this->pairsOf($declared), 'page')) as $pk) {
            $this->sync->ensureCountryRows($countryModel, $pk);
        }

        $rows = PortalContent::where('country_code', $code)
            ->whereIn('page_key', array_unique(array_column($this->pairsOf($declared), 'page')))
            ->orderBy('sort_order')
            ->orderBy('field_key')
            ->get()
            ->groupBy(fn ($r) => $r->page_key."\0".$r->block_key);

        $pageConfig = config("portal_sections.{$pageKey}", []);
        $bands = [];

        foreach ($declared as $band) {
            $blocks = [];
            foreach ($band['blocks'] as $pair) {
                $blockRows = $rows->get($pair['page']."\0".$pair['block']);
                if (! $blockRows) {
                    continue;
                }
                $blocks[] = ['page' => $pair['page'], 'block' => $pair['block'], 'label' => $pair['label_'.$locale] ?? $pair['label_en'] ?? $pair['block'], 'rows' => $blockRows->values()];
            }
            if (! $blocks) {
                continue;
            }
            $bands[] = [
                'key' => $band['key'],
                'label' => $band['label_'.$locale] ?? $band['label_en'] ?? $band['key'],
                'image' => $this->screenshotUrl($band['image'] ?? null, $locale),
                'other' => $band['other'] ?? false,
                'blocks' => $blocks,
            ];
        }

        return view('admin.portal-content.edit', [
            'country' => $countryModel,
            'countries' => $this->activeCountries(),
            'pageKey' => $pageKey,
            'pageLabel' => $pageConfig['label_'.$locale] ?? $pageConfig['label_en'] ?? $pageKey,
            'pages' => $this->pageOptions($locale),
            'bands' => $bands,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => __('admin.portal_content.title'), 'url' => route('admin.portal-content.index')],
                ['label' => $pageConfig['label_'.$locale] ?? $pageKey],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Save — bulk-update the fields of one page for one country only
    // ─────────────────────────────────────────────────────────────────────────

    public function save(Request $request, string $country, string $pageKey): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('portal_content.edit'), 403);

        $countryModel = $this->resolveCountry($country);
        $this->assertKnownPage($pageKey);
        $code = strtolower($countryModel->site_code);

        $request->validate([
            'fields.*.value_url' => ['nullable', 'string', 'max:255'],
            'fields.*.value_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:5120'],
        ]);

        $this->sync->ensureCountryRows($countryModel, $pageKey);

        $allowed = [];
        foreach ($this->pairsOf($this->declaredBands($pageKey)) as $pair) {
            $allowed[$pair['page']."\0".$pair['block']] = true;
            $this->sync->ensureCountryRows($countryModel, $pair['page']);
        }
        $pageKeys = array_unique(array_map(fn ($k) => explode("\0", $k)[0], array_keys($allowed)));

        $count = 0;

        foreach ($request->input('fields', []) as $id => $data) {
            $row = PortalContent::where('country_code', $code)->whereIn('page_key', $pageKeys)->find($id);

            // Only [page_key, block_key] pairs declared in this admin page's bands are editable.
            if (! $row || ! isset($allowed[$row->page_key."\0".$row->block_key])) {
                continue;
            }

            $update = [
                'value_en' => $data['value_en'] ?? null,
                'value_ar' => $data['value_ar'] ?? null,
                'is_active' => isset($data['is_active']),
                'updated_by_admin_id' => $admin->id,
            ];

            if ($row->type === PortalContentType::Link) {
                $update['value_url'] = $data['value_url'] ?? null;
            }

            if ($row->type === PortalContentType::Image) {
                $uploaded = $request->file("fields.{$id}.value_file");

                if ($uploaded) {
                    $update['value_url'] = $uploaded->store('portal-content/'.$row->page_key, 'public');
                } elseif (! empty($data['reset_image'])) {
                    // Empty value_url makes portal_image() fall back to the Blade default.
                    $update['value_url'] = null;
                } else {
                    $update['value_url'] = $data['value_url'] ?? null;
                }
            }

            $row->update($update);
            $count++;
        }

        foreach ($pageKeys as $pk) {
            PortalContent::flush($pk, $code);
        }

        return redirect()->route('admin.portal-content.page', [$code, $pageKey])
            ->with('success', __('admin.portal_content.saved', ['count' => $count, 'country' => $this->countryName($countryModel)]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy (pre per-country) URLs
    // ─────────────────────────────────────────────────────────────────────────

    public function legacyPage(string $pageKey): RedirectResponse
    {
        $this->authorizeView();

        return redirect()->route('admin.portal-content.page', [$this->firstCountry()->site_code, $pageKey]);
    }

    public function legacySave(Request $request, string $pageKey): RedirectResponse
    {
        return $this->save($request, $this->firstCountry()->site_code, $pageKey);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function authorizeView(): void
    {
        abort_unless(auth('admin')->user()->hasPermissionTo('portal_content.view'), 403);
    }

    /** @return Collection<int, Country> */
    private function activeCountries(): Collection
    {
        return Country::where('is_active', true)
            ->whereNotNull('site_code')->where('site_code', '!=', '')
            ->orderBy('name_en')
            ->get();
    }

    private function firstCountry(): Country
    {
        return $this->activeCountries()->first() ?? abort(404);
    }

    private function resolveCountry(string $siteCode): Country
    {
        return Country::where('site_code', strtolower($siteCode))->where('is_active', true)->first() ?? abort(404);
    }

    private function assertKnownPage(string $pageKey): void
    {
        abort_unless(
            config()->has("portal_sections.{$pageKey}") || config()->has("portal_bands.{$pageKey}") || PortalContent::where('page_key', $pageKey)->exists(),
            404
        );
    }

    private function countryName(Country $country): string
    {
        return app()->getLocale() === 'ar' ? ($country->name_ar ?: $country->name_en) : $country->name_en;
    }

    /** @return array<string, string> page_key => label, in portal order (for the page switcher) */
    private function pageOptions(string $locale): array
    {
        $config = config('portal_sections', []);
        $keys = PortalContent::where('country_code', '*')->distinct()->pluck('page_key')->all();
        usort($keys, fn ($a, $b) => [array_search($a, array_keys($config)) === false ? 99999 : array_search($a, array_keys($config)), $a]
            <=> [array_search($b, array_keys($config)) === false ? 99999 : array_search($b, array_keys($config)), $b]);

        $options = [];
        foreach ($keys as $key) {
            $options[$key] = $config[$key]['label_'.$locale] ?? $config[$key]['label_en'] ?? $key;
        }

        return $options;
    }

    /**
     * Bands of an admin page in portal DOM order. Each band lists its blocks as [page, block, labels].
     * Uses config/portal_bands.php; falls back to config/portal_sections.php (each block = own band, no image).
     *
     * @return list<array{key: string, label_en: string, label_ar: string, image: ?string, other?: bool, blocks: list<array<string, mixed>>}>
     */
    private function declaredBands(string $pageKey): array
    {
        $labels = [];
        foreach (config('portal_sections', []) as $pk => $cfg) {
            foreach ($cfg['sections'] ?? [] as $sec) {
                $labels[$pk."\0".$sec['block']] = $sec;
            }
        }
        $mk = fn (string $pk, string $block) => ['page' => $pk, 'block' => $block]
            + array_intersect_key($labels[$pk."\0".$block] ?? [], ['label_en' => 1, 'label_ar' => 1]);

        $bandsCfg = config("portal_bands.{$pageKey}.bands");

        if (is_array($bandsCfg)) {
            return array_map(fn (array $b) => [
                'key' => $b['key'],
                'label_en' => $b['label_en'] ?? $b['key'],
                'label_ar' => $b['label_ar'] ?? ($b['label_en'] ?? $b['key']),
                'image' => $b['image'] ?? null,
                'blocks' => array_map(fn ($pair) => $mk($pair[0], $pair[1]), $b['blocks'] ?? []),
            ], $bandsCfg);
        }

        $bands = [];
        $seen = [];
        foreach (config("portal_sections.{$pageKey}.sections", []) as $sec) {
            $seen[$sec['block']] = true;
            $bands[] = [
                'key' => $sec['block'],
                'label_en' => $sec['label_en'] ?? $sec['block'],
                'label_ar' => $sec['label_ar'] ?? ($sec['label_en'] ?? $sec['block']),
                'image' => null,
                'blocks' => [$mk($pageKey, $sec['block'])],
            ];
        }

        // Blocks present in the DB but not in the sections map: appended alphabetically as "Other".
        $others = PortalContent::where('country_code', '*')->where('page_key', $pageKey)->distinct()->pluck('block_key')
            ->reject(fn ($b) => isset($seen[$b]))->sort()->values();
        foreach ($others as $block) {
            $bands[] = ['key' => $block, 'label_en' => $block, 'label_ar' => $block, 'image' => null, 'other' => true, 'blocks' => [['page' => $pageKey, 'block' => $block]]];
        }

        return $bands;
    }

    /** @return list<array{page: string, block: string}> */
    private function pairsOf(array $bands): array
    {
        return array_merge(...array_map(fn ($b) => $b['blocks'], $bands) ?: [[]]);
    }

    /** Band screenshot for the admin locale -> other locale -> shared placeholder. */
    private function screenshotUrl(?string $image, string $locale): string
    {
        $other = $locale === 'ar' ? 'en' : 'ar';

        if ($image) {
            foreach ([$locale, $other] as $lang) {
                $relative = "images/portal-sections/{$image}.{$lang}.png";
                if (file_exists(public_path($relative))) {
                    return asset($relative);
                }
            }
        }

        return asset("images/portal-sections/_placeholder.{$locale}.svg");
    }
}
