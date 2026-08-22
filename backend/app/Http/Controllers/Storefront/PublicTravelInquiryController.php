<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\TravelPackageInquiryStatus;
use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicTravelInquiryController extends Controller
{
    public function store(Request $request, TravelPackage $package): RedirectResponse
    {
        if ($package->status !== TravelPackageStatus::Active) {
            abort(404);
        }

        $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['required', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'travelers_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'message'         => ['nullable', 'string', 'max:1000'],
        ]);

        $package->inquiries()->create([
            'name'            => $request->input('name'),
            'phone'           => $request->input('phone'),
            'email'           => $request->input('email'),
            'travelers_count' => $request->input('travelers_count'),
            'message'         => $request->input('message'),
            'status'          => TravelPackageInquiryStatus::New,
        ]);

        return back()->with('inquiry_sent', true);
    }
}
