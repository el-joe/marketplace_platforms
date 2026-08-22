<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdSupportArticle;
use App\Models\AdSupportCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdSupportCollectionController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index / Tree
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $roots = AdSupportCollection::with(['children', 'children.articles'])
            ->whereNull('parent_id')
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => AdSupportCollection::count(),
            'active' => AdSupportCollection::where('is_active', true)->count(),
            'top_collection' => AdSupportCollection::withCount('articles')->orderByDesc('articles_count')->first(),
        ];

        return view('admin.adsupport.collections.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.adsupport.collections')],
            ],
            'roots' => $roots,
            'stats' => $stats,
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
            'slug' => 'nullable|string|max:150|unique:ad_support_collections,slug',
            'parent_id' => 'nullable|string|exists:ad_support_collections,id',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('parent_id')) {
            $parent = AdSupportCollection::findOrFail($request->parent_id);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => __('admin.adsupport.parent_must_be_top_level')], 422);
            }
        }

        $slug = $this->uniqueSlug($request->slug ?: Str::slug($request->name_en ?: $request->name));

        $collection = AdSupportCollection::create([
            'parent_id' => $request->parent_id ?: null,
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
            'message' => __('admin.adsupport.collection_created'),
            'collection' => $collection,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, AdSupportCollection $collection): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:150',
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:ad_support_collections,slug,' . $collection->id,
            'parent_id' => 'nullable|string|exists:ad_support_collections,id',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('parent_id')) {
            if ($request->parent_id === $collection->id) {
                return response()->json(['message' => __('admin.adsupport.collection_cannot_be_own_parent')], 422);
            }
            $parent = AdSupportCollection::findOrFail($request->parent_id);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => __('admin.adsupport.parent_must_be_top_level')], 422);
            }
        }

        $slug = $request->filled('slug') ? $this->uniqueSlug($request->slug, $collection->id) : $collection->slug;

        $collection->update([
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name_en ?: $request->name,
            'name_en' => $request->name_en,
            'name_ar' => $request->name_ar,
            'slug' => $slug,
            'icon' => $request->icon ?: null,
            'description' => $request->description_en ?: $request->description,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'sort_order' => (int) ($request->sort_order ?? $collection->sort_order),
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json(['success' => true, 'message' => __('admin.adsupport.collection_updated'), 'collection' => $collection->fresh()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(AdSupportCollection $collection): JsonResponse
    {
        $articleCount = AdSupportArticle::withTrashed()
            ->where('ad_support_collection_id', $collection->id)
            ->count();

        if ($articleCount > 0) {
            return response()->json([
                'message' => __('admin.adsupport.cannot_delete_collection_has_articles', ['count' => $articleCount]),
            ], 422);
        }

        if ($collection->children()->exists()) {
            return response()->json(['message' => __('admin.adsupport.cannot_delete_collection_has_subcollections')], 422);
        }

        $collection->delete();

        return response()->json(['success' => true, 'message' => __('admin.adsupport.collection_deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Active
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleActive(AdSupportCollection $collection): JsonResponse
    {
        $collection->update(['is_active' => !$collection->is_active]);

        return response()->json(['success' => true, 'is_active' => $collection->is_active]);
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
                AdSupportCollection::where('id', $id)->update(['sort_order' => $i]);
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
            AdSupportCollection::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
