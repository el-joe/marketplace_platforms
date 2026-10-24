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

        $this->sync->ensureCountryRows($countryModel, $pageKey);

        $rows = PortalContent::where('country_code', strtolower($countryModel->site_code))
            ->where('page_key', $pageKey)
            ->orderBy('sort_order')
            ->orderBy('field_key')
            ->get()
            ->groupBy('block_key');

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $pageConfig = config("portal_sections.{$pageKey}", []);
        $sections = [];

        foreach ($pageConfig['sections'] ?? [] as $section) {
            if (! $rows->has($section['block'])) {
                continue;
            }
            $sections[] = $this->makeSection($pageKey, $section['block'], $section['label_'.$locale] ?? $section['label_en'] ?? $section['block'], $rows->pull($section['block']), $locale);
        }

        // Unknown blocks: appended alphabetically under "Other".
        foreach ($rows->sortKeys() as $block => $blockRows) {
            $sections[] = $this->makeSection($pageKey, $block, $block, $blockRows, $locale, true);
        }

        return view('admin.portal-content.edit', [
            'country' => $countryModel,
            'countries' => $this->activeCountries(),
            'pageKey' => $pageKey,
            'pageLabel' => $pageConfig['label_'.$locale] ?? $pageConfig['label_en'] ?? $pageKey,
            'pages' => $this->pageOptions($locale),
            'sections' => $sections,
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

        $count = 0;

        foreach ($request->input('fields', []) as $id => $data) {
            $row = PortalContent::where('country_code', $code)->where('page_key', $pageKey)->find($id);

            if (! $row) {
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
                    $update['value_url'] = $uploaded->store('portal-content/'.$pageKey, 'public');
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

        PortalContent::flush($pageKey, $code);

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
            config()->has("portal_sections.{$pageKey}") || PortalContent::where('page_key', $pageKey)->exists(),
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

    private function makeSection(string $pageKey, string $block, string $label, Collection $rows, string $locale, bool $other = false): array
    {
        return [
            'block' => $block,
            'label' => $label,
            'other' => $other,
            'rows' => $rows->values(),
            'image' => $this->screenshotUrl($pageKey, $block, $locale),
        ];
    }

    /** Screenshot for the admin locale → other locale → shared placeholder. */
    private function screenshotUrl(string $pageKey, string $block, string $locale): string
    {
        $other = $locale === 'ar' ? 'en' : 'ar';

        foreach ([$locale, $other] as $lang) {
            $relative = "images/portal-sections/{$pageKey}/{$block}.{$lang}.png";
            if (file_exists(public_path($relative))) {
                return asset($relative);
            }
        }

        return asset("images/portal-sections/_placeholder.{$locale}.svg");
    }
}
