<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromotionCategory;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\PromotionCategoryTarget;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketerSettingsController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        abort_unless(auth('admin')->user()->can('marketer_commission_settings.view'), 403);

        $countries = Country::where('is_active', true)->orderBy('name_ar')->get();

        $influencerFees = MarketerInfluencerFeeCountrySetting::with('country')
            ->get()
            ->keyBy('country_id');

        $promotionTargets = PromotionCategoryTarget::with('country')
            ->orderBy('promotion_category')
            ->get();

        $referralGraceDays = \App\Services\ReferralCodeResolver::graceDays();

        return view('admin.marketer_settings.index', compact(
            'countries', 'influencerFees', 'promotionTargets', 'referralGraceDays'
        ));
    }

    public function updateInfluencerFee(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('marketer_fee_settings.edit'), 403);

        $validated = $request->validate([
            'country_id' => 'required|uuid|exists:countries,id',
            'fee_per_influencer' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
        ]);

        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $validated['country_id']],
            [
                'fee_per_influencer' => $validated['fee_per_influencer'],
                'currency' => $validated['currency'],
                'updated_by_admin_id' => auth('admin')->id(),
            ]
        );

        return back()->with('success', 'تم حفظ رسوم الإنفلوينسر.');
    }

    public function updateReferralGrace(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('marketer_fee_settings.edit'), 403);

        $validated = $request->validate([
            'marketer_referral_grace_days' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'marketer_referral_grace_days'],
            ['value' => (int) $validated['marketer_referral_grace_days'], 'category' => 'marketer', 'is_encrypted' => 0, 'is_public' => 0]
        );
        Setting::flush();

        return back()->with('success', __('admin.marketer_settings.referral_grace_saved'));
    }

    public function updatePromotionTarget(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('marketer_fee_settings.edit'), 403);

        $validated = $request->validate([
            'promotion_category' => ['required', Rule::enum(PromotionCategory::class)],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'monthly_minimum' => ['required', 'integer', 'min:0', 'max:1000'],
            'penalty_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        PromotionCategoryTarget::updateOrCreate(
            ['promotion_category' => $validated['promotion_category'], 'country_id' => $validated['country_id'] ?? null],
            ['monthly_minimum' => $validated['monthly_minimum'], 'penalty_amount' => $validated['penalty_amount'] ?? 0]
        );

        return back()->with('success', __('admin.marketer_settings.promotion_target_saved'));
    }

    public function destroyPromotionTarget(PromotionCategoryTarget $target): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('marketer_fee_settings.edit'), 403);

        $target->delete();

        return back()->with('success', __('admin.marketer_settings.promotion_target_deleted'));
    }
}
