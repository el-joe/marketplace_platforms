<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedWantedListing;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WantedListingController extends Controller
{
    private function marketerId(): string
    {
        return Auth::guard('marketer_api')->user()->marketer->id;
    }

    private function present(ClassifiedWantedListing $w): array
    {
        return [
            'id' => $w->id, 'title_ar' => $w->title_ar,
            'category' => $w->classifiedCategory ? ['id' => $w->classifiedCategory->id, 'name_ar' => $w->classifiedCategory->name_ar] : null,
            'budget_min' => $w->budget_min, 'budget_max' => $w->budget_max,
            'currency' => $w->currency, 'status' => $w->status, 'expires_at' => $w->expires_at,
        ];
    }

    public function index(): JsonResponse
    {
        $page = ClassifiedWantedListing::where('marketer_id', $this->marketerId())
            ->with('classifiedCategory')->latest()->paginate(20);
        $page->getCollection()->transform(fn ($w) => $this->present($w));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classified_category_id' => ['required', 'uuid', 'exists:classified_categories,id'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0', 'gte:budget_min'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $country = Country::findOrFail($data['country_id']);

        $w = ClassifiedWantedListing::create($data + [
            'listing_number' => 'WL-' . strtoupper(Str::random(8)),
            'marketer_id' => $this->marketerId(),
            'currency' => $country->currency_code,
            'status' => 'active',
        ]);

        return response()->json(['success' => true, 'data' => $this->present($w->load('classifiedCategory'))], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $w = ClassifiedWantedListing::where('marketer_id', $this->marketerId())->findOrFail($id);
        $w->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'data' => ['id' => $w->id, 'status' => 'cancelled']]);
    }
}
