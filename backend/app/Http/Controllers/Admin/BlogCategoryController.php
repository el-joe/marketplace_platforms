<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogCategoryController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index / Tree
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $roots = BlogCategory::with(['children', 'children.posts'])
            ->whereNull('parent_id')
            ->withCount('posts')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        $stats = [
            'total'  => BlogCategory::count(),
            'active' => BlogCategory::where('is_active', true)->count(),
            'top_category' => BlogCategory::withCount('posts')->orderByDesc('posts_count')->first(),
        ];

        return view('admin.blog.categories.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.blog_categories')],
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
            'name_en'        => 'required|string|max:150',
            'name_ar'        => 'required|string|max:150',
            'slug'           => 'nullable|string|max:150|unique:blog_categories,slug',
            'parent_id'      => 'nullable|string|exists:blog_categories,id',
            'color_hex'      => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon_name'      => 'nullable|string|max:50',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'seo_title_en'   => 'nullable|string|max:200',
            'seo_title_ar'   => 'nullable|string|max:200',
            'seo_description_en' => 'nullable|string|max:500',
            'seo_description_ar' => 'nullable|string|max:500',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        if ($request->filled('parent_id')) {
            $parent = BlogCategory::findOrFail($request->parent_id);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => __('admin.blog_categories_section.parent_top_level_only')], 422);
            }
        }

        $slug = $this->uniqueSlug($request->slug ?: Str::slug($request->name_en));

        $category = BlogCategory::create([
            'parent_id'      => $request->parent_id ?: null,
            'name_en'        => $request->name_en,
            'name_ar'        => $request->name_ar,
            'slug'           => $slug,
            'color_hex'      => $request->color_hex ?: null,
            'icon_name'      => $request->icon_name ?: null,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'seo_title_en'   => $request->seo_title_en ?: null,
            'seo_title_ar'   => $request->seo_title_ar ?: null,
            'seo_description_en' => $request->seo_description_en ?: null,
            'seo_description_ar' => $request->seo_description_ar ?: null,
            'sort_order'     => (int) ($request->sort_order ?? 0),
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success'  => true,
            'message'  => __('admin.blog_categories_section.created'),
            'category' => $category,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, BlogCategory $category): JsonResponse
    {
        $request->validate([
            'name_en'        => 'required|string|max:150',
            'name_ar'        => 'required|string|max:150',
            'slug'           => 'nullable|string|max:150|unique:blog_categories,slug,' . $category->id,
            'parent_id'      => 'nullable|string|exists:blog_categories,id',
            'color_hex'      => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon_name'      => 'nullable|string|max:50',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'seo_title_en'   => 'nullable|string|max:200',
            'seo_title_ar'   => 'nullable|string|max:200',
            'seo_description_en' => 'nullable|string|max:500',
            'seo_description_ar' => 'nullable|string|max:500',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        if ($request->filled('parent_id')) {
            if ($request->parent_id === $category->id) {
                return response()->json(['message' => __('admin.blog_categories_section.cannot_be_own_parent')], 422);
            }
            $parent = BlogCategory::findOrFail($request->parent_id);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => __('admin.blog_categories_section.parent_top_level_only')], 422);
            }
        }

        $slug = $request->slug ?: $category->slug;
        if (!$request->filled('slug')) {
            $slug = $category->slug;
        } else {
            $slug = $this->uniqueSlug($request->slug, $category->id);
        }

        $category->update([
            'parent_id'      => $request->parent_id ?: null,
            'name_en'        => $request->name_en,
            'name_ar'        => $request->name_ar,
            'slug'           => $slug,
            'color_hex'      => $request->color_hex ?: null,
            'icon_name'      => $request->icon_name ?: null,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'seo_title_en'   => $request->seo_title_en ?: null,
            'seo_title_ar'   => $request->seo_title_ar ?: null,
            'seo_description_en' => $request->seo_description_en ?: null,
            'seo_description_ar' => $request->seo_description_ar ?: null,
            'sort_order'     => (int) ($request->sort_order ?? $category->sort_order),
            'is_active'      => $request->boolean('is_active'),
        ]);

        return response()->json(['success' => true, 'message' => __('admin.blog_categories_section.updated'), 'category' => $category->fresh()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(BlogCategory $category): JsonResponse
    {
        $postCount = \App\Models\BlogPost::withTrashed()
            ->where('blog_category_id', $category->id)
            ->count();

        if ($postCount > 0) {
            return response()->json([
                'message' => __('admin.blog_categories_section.cannot_delete_has_posts', ['count' => $postCount]),
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => __('admin.blog_categories_section.deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Active
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleActive(BlogCategory $category): JsonResponse
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
            'ids'   => 'required|array',
            'ids.*' => 'string',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $i => $id) {
                BlogCategory::where('id', $id)->update(['sort_order' => $i]);
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
            BlogCategory::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
