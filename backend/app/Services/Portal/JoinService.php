<?php

namespace App\Services\Portal;

use App\Enums\JoinSection;
use App\Enums\MarketerType;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and validates the choices made on the portal /join landing page.
 *
 * This is the single server-side source of truth for translating a visitor's
 * (country, section, specialization, marketer type) selection into the correct
 * existing registration destination. It never invents business rules: sections
 * are the client-defined taxonomy ({@see JoinSection}), while countries and
 * specializations are read from their existing tables.
 */
class JoinService
{
    /** Session key holding the last validated join selection. */
    public const SESSION_KEY = 'join.selection';

    /**
     * Countries a visitor may choose to join from.
     *
     * @return Collection<int, Country>
     */
    public function countries(): Collection
    {
        return Country::where('is_active', true)->orderBy('name_ar')->get();
    }

    /**
     * Countries offered in the portal country switcher (active and launched),
     * mirroring the header list. Detection and switching share this set so a
     * visitor is never shown a country that cannot be selected.
     *
     * @return Collection<int, Country>
     */
    public function selectableCountries(): Collection
    {
        return Country::where('is_active', true)
            ->where('is_launched', true)
            ->orderBy('name_en')
            ->get();
    }

    /**
     * The country currently effective for the portal journey: the home-page
     * selection (session `portal_country`), the auto-detected country, or the
     * first launched country. Mirrors the dialog shown on the join landing.
     */
    public function currentCountry(): ?Country
    {
        $countries = $this->selectableCountries();

        return $countries->firstWhere('site_code', Country::resolveSiteCode(null))
            ?? $countries->first();
    }

    /**
     * Selectable sections, keyed by {@see JoinSection} value.
     *
     * @return array<string, string>
     */
    public function sections(): array
    {
        return JoinSection::options();
    }

    /**
     * The landing page a chosen section opens after being validated.
     *
     * @return string named route
     */
    public function sectionLandingRoute(string $section): string
    {
        return match ($section) {
            JoinSection::ProductVendor->value => 'portal.vendors',
            JoinSection::ClassifiedVendor->value => 'portal.companies',
            JoinSection::Marketer->value => 'portal.marketers',
            default => 'portal.join',
        };
    }

    /**
     * Active classified specializations a company/broker may operate under.
     *
     * Classified categories are not country-scoped in the current data model
     * (only product categories are, via country_categories); country-level
     * availability is a pending client decision.
     *
     * @return Collection<int, ClassifiedCategory>
     */
    public function classifiedCategories(): Collection
    {
        return ClassifiedCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * Active marketer types a visitor may register as, keyed by marketer_jobs.key.
     *
     * Only keys that map to a real {@see MarketerType} are exposed, so legacy
     * job keys (celebrity, brand_ambassador) never reach the landing page.
     *
     * @return array<string, string>
     */
    public function marketerTypes(): array
    {
        $isAr = session('locale', 'ar') === 'ar';

        return MarketerJob::where('is_active', true)
            ->whereIn('key', MarketerType::values())
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (MarketerJob $job) => [
                $job->key => $isAr ? $job->name_ar : ($job->name_en ?? $job->name_ar),
            ])
            ->all();
    }

    /**
     * Validate a raw join selection against live data and return the canonical
     * shape. Throws a ValidationException for any invalid or unavailable choice.
     *
     * @param  array<string, mixed>  $input
     * @return array{country_id: string, section: string, classified_category_id: ?string, marketer_type: ?string, entity_type: ?string}
     */
    public function validate(array $input): array
    {
        $validator = Validator::make($input, [
            'country_id' => [
                'required',
                'string',
                Rule::exists('countries', 'id')->where('is_active', true),
            ],
            'section' => ['required', 'string', Rule::in(JoinSection::values())],
            'classified_category_id' => [
                'nullable',
                'string',
                Rule::exists('classified_categories', 'id')->where('is_active', true),
            ],
            'entity_type' => [
                'nullable',
                'string',
                Rule::in(['company', 'broker']),
            ],
            'marketer_type' => [
                'nullable',
                'string',
                Rule::in(array_keys($this->marketerTypes())),
            ],
        ], [
            'country_id.exists' => __('portal.join.country_unavailable'),
            'section.in' => __('portal.join.section_invalid'),
            'classified_category_id.exists' => __('portal.join.category_unavailable'),
            'entity_type.in' => __('portal.join.entity_type_invalid'),
            'marketer_type.in' => __('portal.join.marketer_type_invalid'),
        ]);

        $validator->after(function ($validator) use ($input): void {
            $section = $input['section'] ?? null;

            if ($section === JoinSection::ClassifiedVendor->value && empty($input['classified_category_id'])) {
                $validator->errors()->add('classified_category_id', __('portal.join.category_required'));
            }

            if ($section === JoinSection::ClassifiedVendor->value && empty($input['entity_type'])) {
                $validator->errors()->add('entity_type', __('portal.join.entity_type_required'));
            }

            if ($section === JoinSection::Marketer->value && empty($input['marketer_type'])) {
                $validator->errors()->add('marketer_type', __('portal.join.marketer_type_required'));
            }
        });

        $validated = $validator->validate();

        return [
            'country_id' => $validated['country_id'],
            'section' => $validated['section'],
            'classified_category_id' => $validated['classified_category_id'] ?? null,
            'entity_type' => $validated['entity_type'] ?? null,
            'marketer_type' => $validated['marketer_type'] ?? null,
        ];
    }

    /**
     * Resolve the validated selection to its registration destination.
     *
     * A `ready` result carries a named route + params the controller redirects
     * to. A `pending` result means the section is a valid, client-requested
     * choice but no production-safe registration flow exists for it yet, so we
     * must not activate a partial flow.
     *
     * @param  array<string, mixed>  $validated
     * @return array{status: string, route: ?string, params: array<string, mixed>, message: ?string}
     */
    public function resolve(array $validated): array
    {
        return match ($validated['section']) {
            JoinSection::ProductVendor->value => [
                'status' => 'ready',
                'route' => 'portal.register',
                'params' => [],
                'message' => null,
            ],
            JoinSection::Marketer->value => [
                'status' => 'ready',
                'route' => 'marketer.register',
                'params' => [
                    'type' => $validated['marketer_type'],
                    'country_id' => $validated['country_id'],
                ],
                'message' => null,
            ],
            JoinSection::ClassifiedVendor->value => [
                'status' => 'pending',
                'route' => null,
                'params' => [],
                'message' => __('portal.join.classified_pending'),
            ],
        };
    }

    /**
     * Persist just the section chosen on the first screen, keeping any other
     * previously stored join fields intact.
     */
    public function storeSection(string $section): void
    {
        session([self::SESSION_KEY => array_merge($this->currentSelection(), ['section' => $section])]);
    }

    /**
     * Persist the validated selection in the session, both as the join
     * selection and as a non-destructive prefill for the product vendor wizard.
     *
     * @param  array<string, mixed>  $validated
     */
    public function storeSelection(array $validated): void
    {
        session([self::SESSION_KEY => array_merge($this->currentSelection(), $validated)]);

        if ($validated['section'] !== JoinSection::ProductVendor->value) {
            return;
        }

        if (! session('reg_data.step1.country_id')) {
            session(['reg_data.step1.country_id' => $validated['country_id']]);
        }
    }

    /**
     * The last validated selection, if any (used to re-fill the landing page).
     *
     * @return array<string, mixed>
     */
    public function currentSelection(): array
    {
        return (array) session(self::SESSION_KEY, []);
    }
}
