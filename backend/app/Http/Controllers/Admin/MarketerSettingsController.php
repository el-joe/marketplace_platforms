<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\MarketerInfluencerFeeCountrySetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('admin.marketer_settings.index', compact(
            'countries', 'influencerFees'
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
}
