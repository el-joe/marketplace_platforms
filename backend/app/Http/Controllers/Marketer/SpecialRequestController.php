<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSpecialRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SpecialRequestController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /**
     * List open special requests matching this broker's specialization
     * (category, plus city unless the broker serves all cities or the
     * request itself has no city).
     */
    public function index(): View
    {
        $marketer = $this->marketer();
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        $requests = CustomerSpecialRequest::with(['customer', 'category', 'city'])
            ->where('status', 'open')
            ->where('category_id', $profile->broker_category_id)
            ->when(!$profile->broker_serves_all_cities && $profile->broker_city_id, function ($q) use ($profile) {
                $q->where(function ($q2) use ($profile) {
                    $q2->where('city_id', $profile->broker_city_id)->orWhereNull('city_id');
                });
            })
            ->latest()
            ->paginate(20);

        return view('marketer.special-requests.index', compact('requests'));
    }

    public function show(string $id): View
    {
        $specialRequest = CustomerSpecialRequest::with(['customer', 'category', 'city'])
            ->findOrFail($id);

        return view('marketer.special-requests.show', compact('specialRequest'));
    }
}
