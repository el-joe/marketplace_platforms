<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpCenterCategoryController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index / Tree
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $roots = HelpCenterCategory::with(['children', 'children.articles', 'country'])
            ->whereNull('parent_id')
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => HelpCenterCategory::count(),
            'active' => HelpCenterCategory::where('is_active', true)->count(),
            'top_category' => HelpCenterCategory::withCount('articles')->orderByDesc('articles_count')->first(),
        ];

        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'flag_emoji']);

        return view('admin.helpcenter.categories.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => __('admin.helpcenter.categories')],
            ],
            'roots' => $roots,
            'stats' => $stats,
            'countries' => $countries,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:150',
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:help_center_categories,slug',
            'parent_id' => 'nullable|string|exists:help_center_categories,id',
            'country_id' => 'nullable|string|exists:countries,site_code',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('parent_id')) {
            $parent = HelpCenterCategory::findOrFail($request->parent_id);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => 'Parent category must be a top-level category (max one level of nesting).'], 422);
            }
        }

        $slug = $this->uniqueSlug($request->slug ?: Str::slug($request->name_en ?: $request->name));

        $category = HelpCenterCategory::create([
            'parent_id' => $request->parent_id ?: null,
            'country_id' => $request->country_id ?: null,
            'name' => $request->name_en ?: $request->name,
            'name_en' => $request->name_en,
            'name_ar' => $request->name_ar,
            'slug' => $slug,
            'icon' => $request->icon ?: null,
            'description' => $request->description_en ?: $request->description,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created.',
            'category' => $category,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, HelpCenterCategory $category): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:150',
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:help_center_categories,slug,' . $category->id,
            'parent_id' => 'nullable|string|exists:help_center_categories,id',
            'country_id' => 'nullable|string|exists:countries,site_code',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('parent_id')) {
            if ($request->parent_id === $category->id) {
                return response()->json(['message' => 'A category cannot be its own parent.'], 422);
            }
            $parent = HelpCenterCategory::findOrFail($request->parent_id);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => 'Parent category must be a top-level category (max one level of nesting).'], 422);
            }
        }

        $slug = $request->filled('slug') ? $this->uniqueSlug($request->slug, $category->id) : $category->slug;

        $category->update([
            'parent_id' => $request->parent_id ?: null,
            'country_id' => $request->country_id ?: null,
            'name' => $request->name_en ?: $request->name,
            'name_en' => $request->name_en,
            'name_ar' => $request->name_ar,
            'slug' => $slug,
            'icon' => $request->icon ?: null,
            'description' => $request->description_en ?: $request->description,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'sort_order' => (int) ($request->sort_order ?? $category->sort_order),
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json(['success' => true, 'message' => 'Category updated.', 'category' => $category->fresh()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(HelpCenterCategory $category): JsonResponse
    {
        $articleCount = HelpCenterArticle::withTrashed()
            ->where('help_center_category_id', $category->id)
            ->count();

        if ($articleCount > 0) {
            return response()->json([
                'message' => "Cannot delete this category — it has {$articleCount} article(s) referencing it (including soft-deleted). Deactivate it instead.",
            ], 422);
        }

        if ($category->children()->exists()) {
            return response()->json(['message' => 'Cannot delete this category — it has sub-categories. Delete those first.'], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Active
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleActive(HelpCenterCategory $category): JsonResponse
    {
        $category->update(['is_active' => !$category->is_active]);

        return response()->json(['success' => true, 'is_active' => $category->is_active]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reorder
    // ─────────────────────────────────────────────────────────────────────────

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $i => $id) {
                HelpCenterCategory::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base;
        $i = 1;
        while (
            HelpCenterCategory::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
