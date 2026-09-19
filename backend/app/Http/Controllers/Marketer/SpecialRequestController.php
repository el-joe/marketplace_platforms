<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSpecialRequest;
use App\Models\MarketerProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SpecialRequestController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /** Affiliate-only guard shared by index and show. */
    private function profile(): MarketerProfile
    {
        $marketer = $this->marketer();

        abort_unless($marketer->isAffiliate() && $marketer->global_status?->value === 'active', 403);

        return $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);
    }

    /** List open special requests matching this broker's specialization. */
    public function index(): View
    {
        $profile = $this->profile();

        $requests = CustomerSpecialRequest::matchingBroker($profile)
            ->with(['customer', 'category', 'city'])
            ->latest()
            ->paginate(20);

        $hasSpecialization = (bool) $profile->broker_category_id;

        return view('marketer.special-requests.index', compact('requests', 'hasSpecialization'));
    }

    public function start(string $id)
    {
        $profile = $this->profile();
        $request = CustomerSpecialRequest::matchingBroker($profile)->where('id', $id)->firstOrFail();

        $ok = $request->startProgress();

        return redirect()->route('marketer.special-requests.index')
            ->with($ok ? 'success' : 'error', $ok ? 'OK' : 'Only open requests can be started.');
    }

    public function show(string $id): View
    {
        $profile = $this->profile();

        $specialRequest = CustomerSpecialRequest::matchingBroker($profile)
            ->with(['customer', 'category', 'city'])
            ->where('id', $id)
            ->firstOrFail();

        return view('marketer.special-requests.show', compact('specialRequest'));
    }
}
