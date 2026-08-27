<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Activity;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\File;
use App\Services\CategoryService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use HasDataTable;

    public function __construct(private CategoryService $service)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Tree
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $roots = Category::with(['children'])
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        // Single query for default shipping method per category (for the table column)
        $defaultShippingByCategory = DB::table('category_shipping_methods as csm')
            ->join('shipping_methods as sm', 'sm.id', '=', 'csm.shipping_method_id')
            ->where('csm.is_default', true)
            ->get([
                'csm.category_id',
                'sm.badge_label_en',
                'sm.badge_color_hex',
                'sm.badge_text_color_hex',
                'sm.name',
            ])
            ->keyBy('category_id');

        return view('admin.categories.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.categories')],
            ],
            'roots' => $roots,
            'defaultShippingByCategory' => $defaultShippingByCategory,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.categories.create', $this->formData());
    }

    public function store(StoreCategoryRequest $request): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();
        // try {
        $id = (string) Str::uuid();
        $slug = $request->slug ?: Str::slug($request->name_en) . '-' . Str::lower(Str::random(5));

        // Ensure slug uniqueness
        $i = 1;
        $baseSlug = $slug;
        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        // Insert the node; NestedSet will set lft/rgt after fixTree() or via parent
        if ($request->filled('parent_id')) {
            $parent = Category::findOrFail($request->parent_id);
            $category = new Category([
                'id' => $id,
                'name_en' => $request->name_en,
                'name_ar' => $request->name_ar,
                'slug' => $slug,
                'description_en' => $request->description_en ?: null,
                'description_ar' => $request->description_ar ?: null,
                'commission_rate' => $request->commission_rate ?? $parent->commission_rate ?? 0,
                'commission_fbp_pct' => $request->commission_fbp_pct ?? $parent->commission_fbp_pct ?? 0,
                'commission_fbp_fixed' => (int) ($request->commission_fbp_fixed ?? $parent->commission_fbp_fixed ?? 0),
                'commission_fbn_pct' => $request->commission_fbn_pct ?? $parent->commission_fbn_pct ?? 0,
                'commission_fbn_fixed' => (int) ($request->commission_fbn_fixed ?? $parent->commission_fbn_fixed ?? 0),
                'sort_order' => (int) ($request->sort_order ?? 0),
                'is_active' => $request->boolean('is_active', true),
                'is_visible' => $request->boolean('is_visible', true),
                'is_featured' => $request->boolean('is_featured'),
                'seo_title_en' => $request->seo_title_en ?: null,
                'seo_title_ar' => $request->seo_title_ar ?: null,
                'seo_description_en' => $request->seo_description_en ?: null,
                'seo_description_ar' => $request->seo_description_ar ?: null,
            ]);
            if (!$parent->getLft() || !$parent->getRgt()) {
                Category::fixTree();
                $parent->refresh();
            }
            $category->appendToNode($parent)->save();
        } else {
            $category = Category::create([
                'id' => $id,
                'name_en' => $request->name_en,
                'name_ar' => $request->name_ar,
                'slug' => $slug,
                'description_en' => $request->description_en ?: null,
                'description_ar' => $request->description_ar ?: null,
                'commission_rate' => $request->commission_rate ?? 0,
                'commission_fbp_pct' => $request->commission_fbp_pct ?? 0,
                'commission_fbp_fixed' => (int) ($request->commission_fbp_fixed ?? 0),
                'commission_fbn_pct' => $request->commission_fbn_pct ?? 0,
                'commission_fbn_fixed' => (int) ($request->commission_fbn_fixed ?? 0),
                'sort_order' => (int) ($request->sort_order ?? 0),
                'is_active' => $request->boolean('is_active', true),
                'is_visible' => $request->boolean('is_visible', true),
                'is_featured' => $request->boolean('is_featured'),
                'seo_title_en' => $request->seo_title_en ?: null,
                'seo_title_ar' => $request->seo_title_ar ?: null,
                'seo_description_en' => $request->seo_description_en ?: null,
                'seo_description_ar' => $request->seo_description_ar ?: null,
            ]);
            Category::fixTree();
            $category->refresh();
        }

        // Sync attribute assignments if provided
        if ($request->filled('attributes')) {
            $attributes = array_filter($request->input('attributes', []), fn($a) => isset($a['attribute_id']));
            $this->service->syncAttributes($category, $attributes);
        }

        DB::commit();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.categories.index'),
            ]);
        }

        return redirect()->route('admin.categories.index')
            ->with('success', __('admin.categories.category_created'));

        // } catch (\Throwable $e) {
        //     DB::rollBack();
        //     Log::error('Category creation failed', ['error' => $e->getMessage()]);

        //     if ($request->expectsJson()) {
        //         return response()->json(['message' => 'Failed to create category.'], 500);
        //     }

        //     return back()->withInput()->withErrors(['error' => 'Failed to create category. Please try again.']);
        // }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $category): View
    {
        $categoryModel = Category::with(['categoryAttributes.attribute', 'primaryImage'])
            ->whereNull('deleted_at')
            ->findOrFail($category);

        $marketerCommissions = \App\Models\MarketerCommissionCountrySetting::where('category_id', $categoryModel->id)
            ->with('country')
            ->get()
            ->keyBy('country_id');
        $activeCountries = \App\Models\Country::where('is_active', true)->orderBy('name_en')->get();

        return view('admin.categories.edit', array_merge($this->formData(), [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.categories'), 'url' => route('admin.categories.index')],
                ['label' => e($categoryModel->name_en)],
            ],
            'category' => $categoryModel,
            'marketerCommissions' => $marketerCommissions,
            'activeCountries' => $activeCountries,
        ]));
    }

    public function update(UpdateCategoryRequest $request, string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);


        DB::beginTransaction();
        // try {
        $data = [
            'name_en' => $request->name_en,
            'name_ar' => $request->name_ar,
            'description_en' => $request->description_en ?: null,
            'description_ar' => $request->description_ar ?: null,
            'commission_rate' => $request->commission_rate ?? $categoryModel->commission_rate,
            'commission_fbp_pct' => $request->commission_fbp_pct ?? $categoryModel->commission_fbp_pct ?? 0,
            'commission_fbp_fixed' => (int) ($request->commission_fbp_fixed ?? $categoryModel->commission_fbp_fixed ?? 0),
            'commission_fbn_pct' => $request->commission_fbn_pct ?? $categoryModel->commission_fbn_pct ?? 0,
            'commission_fbn_fixed' => (int) ($request->commission_fbn_fixed ?? $categoryModel->commission_fbn_fixed ?? 0),
            'sort_order' => (int) ($request->sort_order ?? 0),
            'is_active' => $request->boolean('is_active'),
            'is_visible' => $request->boolean('is_visible'),
            'is_featured' => $request->boolean('is_featured'),
            'seo_title_en' => $request->seo_title_en ?: null,
            'seo_title_ar' => $request->seo_title_ar ?: null,
            'seo_description_en' => $request->seo_description_en ?: null,
            'seo_description_ar' => $request->seo_description_ar ?: null,
            'updated_at' => now(),
        ];

        if ($request->filled('slug')) {
            $data['slug'] = $request->slug;
        }

        // Handle parent change via NestedSet
        if ($request->filled('parent_id') && $request->parent_id !== $categoryModel->parent_id) {
            $parent = Category::findOrFail($request->parent_id);
            if (!$parent->getLft() || !$parent->getRgt()) {
                Category::fixTree();
                $parent->refresh();
            }
            $categoryModel->appendToNode($parent);
        } elseif (!$request->filled('parent_id') && $categoryModel->parent_id) {
            $categoryModel->makeRoot();
        }

        $categoryModel->update($data);

        $categoryModel->update($request->only([
            'influencer_sample_qty',
            'affiliate_sample_qty',
            'platform_sample_qty',
            'min_stock_for_campaign',
        ]));

        // Commission rate logging via service
        if ($request->filled('commission_rate')) {
            $oldRate = (float) $categoryModel->getOriginal('commission_rate');
            $newRate = (float) $request->commission_rate;
            if (abs($oldRate - $newRate) > 0.001) {
                Activity::query()->insert([
                    'id' => (string) Str::uuid(),
                    'log_name' => 'categories',
                    'description' => 'commission_changed',
                    'subject_type' => Category::class,
                    'subject_id' => $categoryModel->id,
                    'causer_type' => \App\Models\Admin::class,
                    'causer_id' => auth('admin')->id(),
                    'properties' => json_encode(['old' => $oldRate, 'new' => $newRate]),
                    'event' => 'commission_changed',
                    'created_at' => now(),
                ]);
            }
        }

        // Sync attribute assignments if provided
        if ($request->has('attributes')) {
            // get attributes which have attribute_id
            $attributes = array_filter($request->input('attributes', []), fn($a) => isset($a['attribute_id']));
            $this->service->syncAttributes($categoryModel, $attributes);
        }

        DB::commit();

        return response()->json(['success' => true, 'message' => __('admin.categories.category_updated')]);

        // } catch (\Throwable $e) {
        //     DB::rollBack();
        //     Log::error('Category update failed', ['id' => $category, 'error' => $e->getMessage()]);
        //     return response()->json(['message' => 'Failed to update category.'], 500);
        // }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image Upload
    // ─────────────────────────────────────────────────────────────────────────

    public function uploadImage(Request $request, string $category): JsonResponse
    {
        abort_unless(auth('admin')->user()->can('categories.edit'), 403);

        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        DB::beginTransaction();

        $existing = $categoryModel->primaryImage()->first();
        if ($existing) {
            Storage::disk($existing->storage_type ?: 'public')->delete($existing->path);
            $existing->delete();
        }

        $uploadedFile = $request->file('image');
        $ext = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->guessExtension();
        $path = $uploadedFile->storeAs(
            'categories/' . $categoryModel->id,
            'image_' . Str::random(8) . '.' . $ext,
            'public'
        );

        $file = File::create([
            'key' => 'categories/' . $categoryModel->id . '/image',
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'category_image',
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $ext,
            'size' => $uploadedFile->getSize(),
            'model_type' => Category::class,
            'model_id' => $categoryModel->id,
            'is_primary' => 1,
            'position' => 0,
        ]);

        DB::commit();

        return response()->json([
            'message' => __('admin.categories.image_uploaded'),
            'file_id' => $file->id,
            'url' => $file->full_path,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteImage(Request $request, string $category): JsonResponse
    {
        abort_unless(auth('admin')->user()->can('categories.edit'), 403);

        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $file = $categoryModel->primaryImage()->first()
            ?? $categoryModel->files()->orderBy('position')->first();

        if (!$file) {
            return response()->json(['message' => __('admin.categories.image_not_found')], 404);
        }

        Storage::disk($file->storage_type ?: 'public')->delete($file->path);
        $file->delete();

        return response()->json(['message' => __('admin.categories.image_deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        try {
            $this->service->delete($categoryModel, auth('admin')->id());
            return response()->json(['success' => true, 'message' => __('admin.categories.category_deleted')]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reorder
    // ─────────────────────────────────────────────────────────────────────────

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|string',
            'items.*.sort_order' => 'required|integer',
        ]);

        $this->service->reorder($request->input('items'));

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Featured
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleFeatured(string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        try {
            $this->service->setFeatured($categoryModel, !$categoryModel->is_featured, auth('admin')->id());
            return response()->json(['success' => true, 'is_featured' => $categoryModel->fresh()->is_featured]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Commission Update
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkCommission(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'commission_rate' => 'required|numeric|between:0,100',
        ]);

        $updated = $this->service->bulkUpdateCommission(
            $request->input('ids'),
            (float) $request->commission_rate,
            auth('admin')->id()
        );

        return response()->json(['success' => true, 'message' => trans_choice('admin.categories.bulk_commission_updated', $updated)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sync Attributes for a Category
    // ─────────────────────────────────────────────────────────────────────────

    public function syncAttributes(Request $request, string $category): JsonResponse
    {
        $request->validate([
            'attributes' => 'nullable|array',
            'attributes.*.attribute_id' => 'required|string|exists:attributes,id',
            'attributes.*.is_required' => 'nullable|boolean',
            'attributes.*.sort_order' => 'nullable|integer',
        ]);

        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $this->service->syncAttributes($categoryModel, $request->input('attributes', []));

        return response()->json(['success' => true, 'message' => __('admin.categories.attributes_synced')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marketer Commission per Country
    // ─────────────────────────────────────────────────────────────────────────

    public function updateMarketerCommission(Request $request, Category $category)
    {
        abort_unless(auth('admin')->user()->can('marketer_commission_settings.edit'), 403);
        $request->validate([
            'country_id' => 'required|uuid|exists:countries,id',
            'influencer_commission_amount' => 'required|integer|min:0',
            'affiliate_commission_amount' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
        ]);

        \App\Models\MarketerCommissionCountrySetting::updateOrCreate(
            ['category_id' => $category->id, 'country_id' => $request->country_id],
            [
                'influencer_commission_amount' => $request->integer('influencer_commission_amount'),
                'affiliate_commission_amount' => $request->integer('affiliate_commission_amount'),
                'currency' => $request->currency,
                'updated_by_admin_id' => auth()->guard('admin')->id(),
            ]
        );

        return back()->with('success', __('admin.categories.marketer_commission_saved'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formData(): array
    {
        $parents = Category::whereNull('deleted_at')
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'depth'])
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => str_repeat('— ', $c->depth ?? 0) . $c->name_en,
            ]);

        $allAttributes = Attribute::query()
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'code', 'type']);

        return [
            'parents' => $parents,
            'allAttributes' => $allAttributes,
        ];
    }
}
