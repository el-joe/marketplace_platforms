<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedWantedListing;
use App\Models\Country;
use App\Models\Marketer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WantedListingController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $wanted = ClassifiedWantedListing::where('marketer_id', $this->marketer()->id)
            ->with(['classifiedCategory', 'country'])->latest()->paginate(20);

        return view('marketer.wanted-listings.index', compact('wanted'));
    }

    public function create(): View
    {
        return view('marketer.wanted-listings.create', [
            'categories' => ClassifiedCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'countries'  => Country::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classified_category_id' => ['required', 'uuid', 'exists:classified_categories,id'],
            'country_id'             => ['required', 'uuid', 'exists:countries,id'],
            'title_ar'               => ['required', 'string', 'max:255'],
            'title_en'               => ['nullable', 'string', 'max:255'],
            'description_ar'         => ['nullable', 'string'],
            'budget_min'             => ['nullable', 'integer', 'min:0'],
            'budget_max'             => ['nullable', 'integer', 'min:0', 'gte:budget_min'],
            'expires_at'             => ['nullable', 'date', 'after:now'],
        ]);

        $country = Country::findOrFail($data['country_id']);

        ClassifiedWantedListing::create($data + [
            'listing_number' => 'WL-' . strtoupper(Str::random(8)),
            'marketer_id'    => $this->marketer()->id,
            'currency'       => $country->currency_code,
            'status'         => 'active',
        ]);

        return redirect()->route('marketer.wanted-listings.index')->with('success', __('marketer.wanted_created'));
    }

    public function destroy(string $wanted): RedirectResponse
    {
        $wanted = ClassifiedWantedListing::where('marketer_id', $this->marketer()->id)->findOrFail($wanted);
        $wanted->update(['status' => 'cancelled']);

        return back()->with('success', __('marketer.wanted_cancelled'));
    }
}
