<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelInclusion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TravelInclusionController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelInclusion::query()
            ->withCount('packages')
            ->when($request->input('q'), fn($q, $v) =>
                $q->where('name_en', 'like', "%{$v}%")
                  ->orWhere('name_ar', 'like', "%{$v}%")
            )
            ->when($request->filled('active'), fn($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('sort_order')
            ->orderBy('name_en');

        $inclusions = $query->paginate(50)->withQueryString();

        return view('admin.travel.inclusions.index', compact('inclusions'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]);

        $data['slug'] = $this->resolveSlug($data['name_en']);

        $inclusion = TravelInclusion::create($data);

        return response()->json(['message' => 'Inclusion created.', 'inclusion' => $inclusion], 201);
    }

    public function update(Request $request, TravelInclusion $travelInclusion): JsonResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]);

        $travelInclusion->update($data);

        return response()->json(['message' => 'Inclusion updated.', 'inclusion' => $travelInclusion]);
    }

    public function destroy(TravelInclusion $travelInclusion): JsonResponse
    {
        if ($travelInclusion->packages()->exists()) {
            return response()->json(['message' => 'Cannot delete: travel packages reference this inclusion.'], 422);
        }

        $travelInclusion->delete();

        return response()->json(['message' => 'Inclusion deleted.']);
    }

    private function resolveSlug(string $nameEn): string
    {
        $base = Str::slug($nameEn);
        $slug = $base;
        $i = 1;
        while (TravelInclusion::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
