<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TravelCountryController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelCountry::query()
            ->withCount(['cities', 'packages'])
            ->when($request->input('q'), fn($q, $v) =>
                $q->where('name_en', 'like', "%{$v}%")
                  ->orWhere('name_ar', 'like', "%{$v}%")
                  ->orWhere('iso_code_2', 'like', "%{$v}%")
            )
            ->when($request->input('continent'), fn($q, $v) => $q->where('continent', $v))
            ->when($request->filled('active'), fn($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name_en');

        $countries  = $query->paginate(50)->withQueryString();
        $continents = TravelCountry::distinct()->pluck('continent')->filter()->sort()->values();

        return view('admin.travel.countries.index', compact('countries', 'continents'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'iso_code_2'  => ['required', 'size:2', 'unique:travel_countries,iso_code_2'],
            'iso_code_3'  => ['required', 'size:3', 'unique:travel_countries,iso_code_3'],
            'name_en'     => ['required', 'string', 'max:100'],
            'name_ar'     => ['required', 'string', 'max:100'],
            'flag_emoji'  => ['nullable', 'string', 'max:10'],
            'continent'   => ['nullable', 'string', 'max:50'],
            'is_active'   => ['boolean'],
        ]);

        $country = TravelCountry::create($data);

        return response()->json(['message' => 'Country created.', 'country' => $country], 201);
    }

    public function update(Request $request, TravelCountry $travelCountry): JsonResponse
    {
        $data = $request->validate([
            'iso_code_2'  => ['required', 'size:2', Rule::unique('travel_countries', 'iso_code_2')->ignore($travelCountry)],
            'iso_code_3'  => ['required', 'size:3', Rule::unique('travel_countries', 'iso_code_3')->ignore($travelCountry)],
            'name_en'     => ['required', 'string', 'max:100'],
            'name_ar'     => ['required', 'string', 'max:100'],
            'flag_emoji'  => ['nullable', 'string', 'max:10'],
            'continent'   => ['nullable', 'string', 'max:50'],
            'is_active'   => ['boolean'],
        ]);

        $travelCountry->update($data);

        return response()->json(['message' => 'Country updated.', 'country' => $travelCountry]);
    }

    public function destroy(TravelCountry $travelCountry): JsonResponse
    {
        if ($travelCountry->packages()->exists()) {
            return response()->json(['message' => 'Cannot delete: travel packages reference this country.'], 422);
        }

        if ($travelCountry->cities()->exists()) {
            return response()->json(['message' => 'Cannot delete: cities exist for this country. Remove them first.'], 422);
        }

        $travelCountry->delete();

        return response()->json(['message' => 'Country deleted.']);
    }
}
