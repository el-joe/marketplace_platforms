<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use ResolvesTravelAgency;

    public function edit(): View
    {
        $agency = $this->agency();

        return view('travel-agency.profile.edit', compact('agency'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->requireOwner();
        $agency = $this->agency();

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'logo'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($request->hasFile('logo')) {
            if ($agency->logo_path) {
                Storage::disk('public')->delete($agency->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('travel-agency-logos', 'public');
        }

        unset($data['logo']);

        $agency->update($data);

        return back()->with('success', __('travel.profile.updated'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $member = $this->member();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $member->password)) {
            return back()->withErrors(['current_password' => __('travel.profile.current_password_incorrect')]);
        }

        $member->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', __('travel.profile.password_updated'));
    }
}
