<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClassifiedCategoryController extends Controller
{
    public function index(): View
    {
        $roots = ClassifiedCategory::with(['children.contractTemplate', 'contractTemplate'])
            ->withCount(['listings as active_listing_count' => fn($q) => $q->where('status', 'active')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        // Also eager-load children listing counts
        $roots->each(function ($root) {
            $root->children->each(function ($child) {
                $child->loadCount(['listings as active_listing_count' => fn($q) => $q->where('status', 'active')]);
            });
        });

        $templates = ClassifiedContractTemplate::where('is_active', true)->orderBy('name')->get();

        return view('admin.classified-categories.index', compact('roots', 'templates'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_en'                     => 'required|string|max:150',
            'name_ar'                     => 'required|string|max:150',
            'slug'                        => 'nullable|string|max:150|unique:classified_categories,slug',
            'icon'                        => 'nullable|string|max:50',
            'parent_id'                   => 'nullable|uuid|exists:classified_categories,id',
            'requires_location_map'       => 'boolean',
            'requires_sketch_upload'      => 'boolean',
            'contract_template_id'        => 'nullable|uuid|exists:classified_contract_templates,id',
            'required_attachment_types'   => 'nullable|array',
            'required_attachment_types.*' => 'string|max:100',
            'is_active'                   => 'boolean',
            'sort_order'                  => 'integer|min:0',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name_en']);

        // Cycle guard: a category cannot be its own ancestor
        if (!empty($validated['parent_id'])) {
            if ($this->wouldCreateCycle(null, $validated['parent_id'])) {
                return response()->json(['message' => __('admin.classified_categories.parent_cycle')], 422);
            }
        }

        $category = ClassifiedCategory::create($validated);

        return response()->json([
            'message'  => __('admin.classified_categories.created'),
            'category' => $category->load('contractTemplate'),
        ], 201);
    }

    public function update(Request $request, ClassifiedCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name_en'                     => 'required|string|max:150',
            'name_ar'                     => 'required|string|max:150',
            'slug'                        => 'nullable|string|max:150|unique:classified_categories,slug,' . $category->id,
            'icon'                        => 'nullable|string|max:50',
            'parent_id'                   => 'nullable|uuid|exists:classified_categories,id',
            'requires_location_map'       => 'boolean',
            'requires_sketch_upload'      => 'boolean',
            'contract_template_id'        => 'nullable|uuid|exists:classified_contract_templates,id',
            'required_attachment_types'   => 'nullable|array',
            'required_attachment_types.*' => 'string|max:100',
            'is_active'                   => 'boolean',
            'sort_order'                  => 'integer|min:0',
        ]);

        if (!empty($validated['parent_id'])) {
            if ($this->wouldCreateCycle($category->id, $validated['parent_id'])) {
                return response()->json(['message' => __('admin.classified_categories.parent_cycle')], 422);
            }
        }

        $category->update($validated);

        return response()->json([
            'message'  => __('admin.classified_categories.updated'),
            'category' => $category->load('contractTemplate'),
        ]);
    }

    public function destroy(ClassifiedCategory $category): JsonResponse
    {
        if ($category->children()->exists()) {
            return response()->json([
                'message' => __('admin.classified_categories.cannot_delete_has_children'),
            ], 422);
        }

        if ($category->listings()->exists()) {
            return response()->json([
                'message' => __('admin.classified_categories.cannot_delete_has_listings'),
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => __('admin.classified_categories.deleted')]);
    }

    public function toggleActive(ClassifiedCategory $category): JsonResponse
    {
        $category->update(['is_active' => !$category->is_active]);

        $statusLabel = $category->is_active
            ? __('admin.classified_categories.activated')
            : __('admin.classified_categories.deactivated');

        return response()->json([
            'message'   => __('admin.classified_categories.status_toggled', ['status' => $statusLabel]),
            'is_active' => $category->is_active,
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'uuid',
        ]);

        \DB::transaction(function () use ($request) {
            foreach ($request->ids as $order => $id) {
                ClassifiedCategory::where('id', $id)->update(['sort_order' => $order]);
            }
        });

        return response()->json(['message' => __('admin.classified_categories.order_saved')]);
    }

    private function wouldCreateCycle(?string $categoryId, string $proposedParentId): bool
    {
        // Walk up the tree from proposedParent; if we hit $categoryId, it's a cycle
        $current = $proposedParentId;
        $visited = [];

        while ($current !== null) {
            if ($categoryId && $current === $categoryId) {
                return true;
            }
            if (isset($visited[$current])) {
                break; // existing cycle in data — stop
            }
            $visited[$current] = true;
            $current = ClassifiedCategory::where('id', $current)->value('parent_id');
        }

        return false;
    }
}
