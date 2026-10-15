<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionVertical;
use App\Http\Controllers\Controller;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerDefaultCommission;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\Setting;
use App\Models\TravelCategory;
use App\Services\LastClickAttributionService;
use App\Services\ReferralCodeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin > Marketer Settings. Tabs:
 *  1. Default influencer commission - fixed amount PER CAMPAIGN by country x vertical (+ optional per-category override rows)
 *  2. Commission fees               - platform fee (pct + fixed) taken from every marketer commission, by country x vertical
 *  3. Campaign fees                 - summary of / link to the Marketing Fees page (fixed + variable campaign fee)
 *  4. General                       - referral grace, payout clearing days, attribution window, peer promotion min stock
 *
 * Defaults live in marketer_default_commissions (one row per country x vertical, plus optional category rows).
 */
class MarketerSettingsController extends Controller
{
    use AuthorizesRequests;

    public const TABS = ['influencer', 'fees', 'campaign', 'general'];

    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('marketer_commission_settings.view'), 403);

        $countries = Country::where('is_active', true)->orderBy('name_ar')->get();
        $verticals = CommissionVertical::cases();

        $rows = MarketerDefaultCommission::query()->whereNull('category_id')->get()
            ->keyBy(fn (MarketerDefaultCommission $r) => $r->country_id.':'.$r->category_type->value);

        $overrides = MarketerDefaultCommission::query()->whereNotNull('category_id')
            ->with('country')->orderBy('country_id')->orderBy('category_type')->get()
            ->each(fn (MarketerDefaultCommission $r) => $r->setAttribute('category_name', $this->categoryName($r)));

        $categoryOptions = [
            CommissionVertical::Product->value => $this->options(Category::query()),
            CommissionVertical::Classified->value => $this->options(ClassifiedCategory::query()),
            CommissionVertical::Travel->value => $this->options(TravelCategory::query()),
        ];

        $campaignFees = [
            'fixed_rows' => MarketingFeeCountrySetting::query()->where(fn ($q) => $q->where('fixed_amount', '>', 0)->orWhere('fixed_percentage', '>', 0))->count(),
            'variable_rows' => MarketingFeeCategorySetting::query()->count(),
        ];

        $general = [
            'marketer_referral_grace_days' => ReferralCodeResolver::graceDays(),
            'marketer_payout_clearing_days' => max(0, (int) setting('marketer_payout_clearing_days', ReleaseMarketerPendingCommissionJob::DEFAULT_CLEARING_DAYS)),
            'marketer_classified_approval_days' => max(0, (int) setting('marketer_classified_approval_days', ApproveMarketerConversionsJob::DEFAULT_CLASSIFIED_APPROVAL_DAYS)),
            'marketer_attribution_window_days' => max(1, (int) setting('marketer_attribution_window_days', LastClickAttributionService::DEFAULT_ATTRIBUTION_WINDOW_DAYS)),
            'marketer_peer_campaign_min_stock' => max(1, (int) setting('marketer_peer_campaign_min_stock', 1)),
        ];

        $tab = $request->query('tab', session('tab'));
        $tab = in_array($tab, self::TABS, true) ? $tab : 'influencer';

        $canEdit = auth('admin')->user()->can('marketer_fee_settings.edit');

        return view('admin.marketer_settings.index', compact(
            'countries', 'verticals', 'rows', 'overrides', 'categoryOptions', 'campaignFees', 'general', 'tab', 'canEdit'
        ));
    }

    /** Tab 1: the fixed amount per campaign an influencer earns, for every country x vertical cell of the grid. */
    public function updateInfluencerDefaults(Request $request): RedirectResponse
    {
        $this->authorizeEdit();

        $validated = $request->validate([
            'amounts' => ['required', 'array'],
            'amounts.*' => ['array'],
            'amounts.*.*' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ]);

        $this->saveGrid($validated['amounts'], 'influencer_fixed_amount');

        return $this->back('influencer', __('marketer_settings.influencer.saved'));
    }

    /** Tab 1: a per-category override of the vertical-wide influencer amount. */
    public function storeInfluencerOverride(Request $request): RedirectResponse
    {
        $this->authorizeEdit();

        $validated = $request->validate([
            'country_id' => ['required', 'uuid', Rule::exists('countries', 'id')],
            'category_type' => ['required', Rule::enum(CommissionVertical::class)],
            'category_id' => ['required', 'uuid'],
            'influencer_fixed_amount' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ]);

        $vertical = CommissionVertical::from($validated['category_type']);
        if (! $this->categoryModel($vertical)::query()->whereKey($validated['category_id'])->exists()) {
            return $this->back('influencer')->withErrors(['category_id' => __('marketer_settings.influencer.override_category_invalid')]);
        }

        $row = MarketerDefaultCommission::firstOrNew([
            'country_id' => $validated['country_id'],
            'category_type' => $vertical->value,
            'category_id' => $validated['category_id'],
        ]);
        $row->influencer_fixed_amount = round((float) $validated['influencer_fixed_amount'], 2);
        $row->updated_by_admin_id = auth('admin')->id();
        $row->save();

        return $this->back('influencer', __('marketer_settings.influencer.override_saved'));
    }

    public function destroyInfluencerOverride(MarketerDefaultCommission $override): RedirectResponse
    {
        $this->authorizeEdit();
        abort_if($override->category_id === null, 404);

        $override->delete();

        return $this->back('influencer', __('marketer_settings.influencer.override_deleted'));
    }

    /** Tab 2: the platform commission fee (percentage + fixed) for every country x vertical cell. */
    public function updateCommissionFees(Request $request): RedirectResponse
    {
        $this->authorizeEdit();

        $validated = $request->validate([
            'fees' => ['required', 'array'],
            'fees.*' => ['array'],
            'fees.*.*' => ['array'],
            'fees.*.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'fees.*.*.fixed' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['fees'] as $countryId => $byVertical) {
                $this->assertCountry($countryId);
                foreach ($byVertical as $vertical => $values) {
                    $this->assertVertical($vertical);
                    $percentage = round((float) ($values['percentage'] ?? 0), 4);
                    $fixed = round((float) ($values['fixed'] ?? 0), 2);

                    $row = MarketerDefaultCommission::query()
                        ->where('country_id', $countryId)->where('category_type', $vertical)->whereNull('category_id')->first();

                    if (! $row && $percentage == 0.0 && $fixed == 0.0) {
                        continue;
                    }

                    $row ??= new MarketerDefaultCommission(['country_id' => $countryId, 'category_type' => $vertical]);
                    $row->commission_fee_percentage = $percentage;
                    $row->commission_fee_fixed = $fixed;
                    $row->updated_by_admin_id = auth('admin')->id();
                    $row->save();
                }
            }
        });

        return $this->back('fees', __('marketer_settings.fees.saved'));
    }

    /** Tab 4: referral grace, payout clearing, attribution window and peer-promotion minimum stock. */
    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->authorizeEdit();

        $validated = $request->validate([
            'marketer_referral_grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'marketer_payout_clearing_days' => ['required', 'integer', 'min:0', 'max:60'],
            'marketer_classified_approval_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'marketer_attribution_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'marketer_peer_campaign_min_stock' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        // Optional fields left blank keep their current value.
        $validated = array_filter($validated, fn ($value) => $value !== null);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (int) $value, 'category' => 'marketer', 'is_encrypted' => 0, 'is_public' => 0]
            );
        }
        Setting::flush();

        return $this->back('general', __('marketer_settings.general.saved'));
    }

    /**
     * @param  array<string, array<string, mixed>>  $grid  country_id => vertical => amount
     */
    private function saveGrid(array $grid, string $column): void
    {
        DB::transaction(function () use ($grid, $column) {
            foreach ($grid as $countryId => $byVertical) {
                $this->assertCountry($countryId);
                foreach ($byVertical as $vertical => $amount) {
                    $this->assertVertical($vertical);
                    $amount = round((float) ($amount ?? 0), 2);

                    $row = MarketerDefaultCommission::query()
                        ->where('country_id', $countryId)->where('category_type', $vertical)->whereNull('category_id')->first();

                    // Never create an all-zero row just because the grid was submitted with blanks.
                    if (! $row && $amount == 0.0) {
                        continue;
                    }

                    $row ??= new MarketerDefaultCommission(['country_id' => $countryId, 'category_type' => $vertical]);
                    $row->{$column} = $amount;
                    $row->updated_by_admin_id = auth('admin')->id();
                    $row->save();
                }
            }
        });
    }

    private function assertCountry(string $countryId): void
    {
        abort_unless(Country::whereKey($countryId)->exists(), 422);
    }

    private function assertVertical(string $vertical): void
    {
        abort_unless(CommissionVertical::tryFrom($vertical) !== null, 422);
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth('admin')->user()->can('marketer_fee_settings.edit'), 403);
    }

    private function back(string $tab, ?string $message = null): RedirectResponse
    {
        $response = redirect()->route('admin.marketer-settings.index', ['tab' => $tab]);

        return $message ? $response->with('success', $message) : $response;
    }

    /** @return class-string<Model> */
    private function categoryModel(CommissionVertical $vertical): string
    {
        return match ($vertical) {
            CommissionVertical::Classified => ClassifiedCategory::class,
            CommissionVertical::Travel => TravelCategory::class,
            CommissionVertical::Product => Category::class,
        };
    }

    private function categoryName(MarketerDefaultCommission $row): string
    {
        $category = $this->categoryModel($row->category_type)::query()->find($row->category_id);

        return $category ? ($category->name_ar ?: $category->name_en) : __('marketer_settings.influencer.override_category_missing');
    }

    /** @return list<array{id: string, name: string}> */
    private function options($query): array
    {
        return $query->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name_ar ?: $c->name_en])->values()->all();
    }
}
