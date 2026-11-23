<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionVertical;
use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerDefaultCommission;
use App\Models\MarketingFeeVerticalSetting;
use App\Services\Marketer\MarketerDefaultCommissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * WS12 #25: commission settings of ONE classified category, per country, on the classified category page.
 *
 * Single source of truth: the same `marketer_default_commissions` rows (category_type = classified) that Admin →
 * Marketer Settings reads and writes — nothing is copied. A blank field stores NULL = inherit (parent section, then
 * the country default), so a new category inherits everything automatically.
 *
 * Classified sales happen off the platform (ClassifiedSaleService), so there is no platform commission on the sale
 * itself; the platform earns the campaign fee (per country for the whole vertical) and the commission fee shown here.
 */
class ClassifiedCategoryCommissionController extends Controller
{
    private const FIELDS = ['influencer_fixed_amount', 'commission_fee_percentage', 'commission_fee_fixed'];

    public function edit(ClassifiedCategory $category): View
    {
        $countries = Country::query()->where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'currency_code']);
        $rows = MarketerDefaultCommission::query()
            ->where('category_type', CommissionVertical::Classified->value)
            ->where('category_id', $category->id)
            ->get()
            ->keyBy('country_id');

        $resolver = app(MarketerDefaultCommissionResolver::class);
        $parentId = $category->parent_id;
        $inherited = $countries->mapWithKeys(fn (Country $country) => [$country->id => [
            'influencer' => $resolver->influencerFixed($country->id, CommissionVertical::Classified, $parentId),
            'fee' => $resolver->commissionFee($country->id, CommissionVertical::Classified, $parentId),
            'campaign_fee' => MarketingFeeVerticalSetting::forVertical($country->id, CommissionVertical::Classified),
        ]]);

        return view('admin.classified-categories.commission', [
            'category' => $category->load('parent'),
            'countries' => $countries,
            'rows' => $rows,
            'inherited' => $inherited,
            'canEdit' => (bool) auth('admin')->user()?->can('marketer_fee_settings.edit'),
        ]);
    }

    public function update(Request $request, ClassifiedCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['nullable', 'array'],
            'settings.*' => ['array'],
            'settings.*.influencer_fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'settings.*.commission_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'settings.*.commission_fee_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
        ]);

        $validated['settings'] ??= [];
        $countryIds = Country::query()->whereIn('id', array_keys($validated['settings']))->pluck('id')->all();

        DB::transaction(function () use ($validated, $countryIds, $category) {
            foreach ($countryIds as $countryId) {
                $values = collect(self::FIELDS)->mapWithKeys(function (string $field) use ($validated, $countryId) {
                    $value = $validated['settings'][$countryId][$field] ?? null;

                    return [$field => ($value === null || $value === '') ? null : round((float) $value, $field === 'commission_fee_percentage' ? 4 : 2)];
                });

                $row = MarketerDefaultCommission::query()
                    ->where('country_id', $countryId)
                    ->where('category_type', CommissionVertical::Classified->value)
                    ->where('category_id', $category->id)
                    ->first();

                // All blank = inherit everything: no row at all.
                if ($values->every(fn ($value) => $value === null)) {
                    $row?->delete();

                    continue;
                }

                $row ??= new MarketerDefaultCommission([
                    'country_id' => $countryId,
                    'category_type' => CommissionVertical::Classified->value,
                    'category_id' => $category->id,
                ]);
                $row->forceFill($values->all());
                $row->updated_by_admin_id = auth('admin')->id();
                $row->save();
            }
        });

        return redirect()->route('admin.classifieds.categories.commission.edit', $category)
            ->with('success', __('classified_commission.saved'));
    }
}
