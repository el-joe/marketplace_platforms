<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelCity;
use App\Models\TravelCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelCityController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelCity::query()
            ->with('country')
            ->withCount('packages')
            ->when($request->input('q'), fn($q, $v) =>
                $q->where('name_en', 'like', "%{$v}%")
                  ->orWhere('name_ar', 'like', "%{$v}%")
            )
            ->when($request->input('country_id'), fn($q, $v) => $q->where('travel_country_id', $v))
            ->when($request->filled('active'), fn($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name_en');

        $cities    = $query->paginate(50)->withQueryString();
        $countries = TravelCountry::orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.travel.cities.index', compact('cities', 'countries'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'travel_country_id' => ['required', 'uuid', 'exists:travel_countries,id'],
            'name_en'           => ['required', 'string', 'max:100'],
            'name_ar'           => ['required', 'string', 'max:100'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'is_active'         => ['boolean'],
        ]);

        $city = TravelCity::create($data);
        $city->load('country');

        return response()->json(['message' => 'City created.', 'city' => $city], 201);
    }

    public function update(Request $request, TravelCity $travelCity): JsonResponse
    {
        $data = $request->validate([
            'travel_country_id' => ['required', 'uuid', 'exists:travel_countries,id'],
            'name_en'           => ['required', 'string', 'max:100'],
            'name_ar'           => ['required', 'string', 'max:100'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'is_active'         => ['boolean'],
        ]);

        $travelCity->update($data);

        return response()->json(['message' => 'City updated.', 'city' => $travelCity->fresh('country')]);
    }

    public function destroy(TravelCity $travelCity): JsonResponse
    {
        if ($travelCity->packages()->exists()) {
            return response()->json(['message' => 'Cannot delete: travel packages reference this city.'], 422);
        }

        $travelCity->delete();

        return response()->json(['message' => 'City deleted.']);
    }
}
