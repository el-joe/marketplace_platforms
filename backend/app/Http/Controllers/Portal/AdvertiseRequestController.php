<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdvertiseInquiry;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvertiseRequestController extends Controller
{
    public function store(Request $request, ?string $country = null): RedirectResponse
    {
        $country = Country::resolveSiteCode($country);

        $noSpecialChars = 'regex:/^[^+\-*\/]*$/';

        $messages = [
            'name.regex' => __('portal.advertise_request.special_chars_not_allowed'),
            'company_name.regex' => __('portal.advertise_request.special_chars_not_allowed'),
            'phone.regex' => __('portal.advertise_request.invalid_phone'),
            'description.min' => __('portal.advertise_request.description_min'),
            'description.max' => __('portal.advertise_request.description_max'),
            'name.required' => __('portal.advertise_request.name_required'),
            'email.required' => __('portal.advertise_request.email_required'),
            'email.email' => __('portal.advertise_request.email_required'),
            'company_name.required' => __('portal.advertise_request.company_name_required'),
            'description.required' => __('portal.advertise_request.description_required'),
        ];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $noSpecialChars],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['required', 'string', 'max:255', $noSpecialChars],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'description' => ['required', 'string', 'min:50', 'max:1000'],
        ], $messages);

        AdvertiseInquiry::create([
            'country' => $country,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'],
            'phone' => $validated['phone'] ?? null,
            'description' => $validated['description'],
        ]);

        return redirect()
            ->route('portal.advertise.request', $country)
            ->with('advertise_request_success', true);
    }
}
