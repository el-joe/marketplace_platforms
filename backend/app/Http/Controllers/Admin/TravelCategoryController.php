<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TravelCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = TravelCategory::withCount('packages')
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('packages')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        return view('admin.travel.categories.index', compact('categories'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'uuid', 'exists:travel_categories,id'],
            'name_en'   => ['required', 'string', 'max:150'],
            'name_ar'   => ['required', 'string', 'max:150'],
            'icon'      => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'sort_order'=> ['integer', 'min:0'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['name_en']);

        $category = TravelCategory::create($data);

        return response()->json([
            'message'  => __('admin.travel.category_created'),
            'category' => $category,
        ], 201);
    }

    public function update(Request $request, TravelCategory $travelCategory): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'uuid', Rule::exists('travel_categories', 'id')
                ->whereNot('id', $travelCategory->id)],
            'name_en'   => ['required', 'string', 'max:150'],
            'name_ar'   => ['required', 'string', 'max:150'],
            'icon'      => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'sort_order'=> ['integer', 'min:0'],
        ]);

        if ($data['name_en'] !== $travelCategory->name_en) {
            $data['slug'] = $this->uniqueSlug($data['name_en'], $travelCategory->id);
        }

        $travelCategory->update($data);

        return response()->json([
            'message'  => __('admin.travel.category_updated'),
            'category' => $travelCategory->fresh(),
        ]);
    }

    public function destroy(TravelCategory $travelCategory): JsonResponse
    {
        if ($travelCategory->packages()->exists()) {
            return response()->json([
                'message' => __('admin.travel.category_delete_has_packages'),
            ], 422);
        }

        if ($travelCategory->children()->exists()) {
            return response()->json([
                'message' => __('admin.travel.category_delete_has_children'),
            ], 422);
        }

        $travelCategory->delete();

        return response()->json(['message' => __('admin.travel.category_deleted')]);
    }

    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (
            TravelCategory::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-" . $i++;
        }

        return $slug;
    }
}
