<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\DeliveryAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(): View
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user()->load('documents', 'zone.country');

        return view('delivery.profile.index', compact('agent'));
    }

    // ── GET /me/zone ─────────────────────────────────────────────────────────
    // Returns the authenticated agent's zone, including its covered cities and
    // fees, so the agent can see the territory and rates they operate under.

    public function zone(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user()->load('zone.country');

        if (!$agent->zone) {
            return response()->json(['zone' => null]);
        }

        $cities = !empty($agent->zone->city_ids)
            ? City::whereIn('id', $agent->zone->city_ids)->orderBy('name_en')->get(['id', 'name_en', 'name_ar'])
            : collect();

        return response()->json([
            'zone' => [
                'id' => $agent->zone->id,
                'name' => $agent->zone->name,
                'code' => $agent->zone->code,
                'country' => $agent->zone->country?->name_en,
                'currency_code' => $agent->zone->country?->currency_code,
                'base_delivery_fee' => $agent->zone->base_delivery_fee,
                'cod_fee' => $agent->zone->cod_fee,
                'cities' => $cities,
            ],
        ]);
    }

    public function changePassword(Request $request): RedirectResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($validated['current_password'], $agent->password)) {
            return back()->withErrors(['current_password' => __('delivery.messages.profile.current_password_incorrect')]);
        }

        $agent->update(['password' => $validated['password']]);

        return back()->with('success', __('delivery.messages.profile.password_updated'));
    }
}
