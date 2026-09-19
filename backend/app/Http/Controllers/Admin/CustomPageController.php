<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\File;
use App\Models\Slug;
use App\Services\CustomPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomPageController extends Controller
{
    public function __construct(private CustomPageService $service)
    {
    }

    public function index(): View
    {
        $customPages = CustomPage::withoutTrashed()
            ->with(['slugRecord', 'primaryImage'])
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        return view('admin.custom-pages.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.custom_pages')],
            ],
            'customPages' => $customPages,
        ]);
    }

    public function create(): View
    {
        return view('admin.custom-pages.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.custom_pages'), 'url' => route('admin.custom-pages.index')],
                ['label' => __('admin.custom_pages.create_title')],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'has_filters' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'seo_title_en' => 'nullable|string|max:255',
            'seo_title_ar' => 'nullable|string|max:255',
            'seo_description_en' => 'nullable|string|max:255',
            'seo_description_ar' => 'nullable|string|max:255',
            'listing_types' => 'nullable|array',
            'listing_types.*' => ['string', \Illuminate\Validation\Rule::in(CustomPage::LISTING_TYPES)],
            'all_categories' => 'nullable|boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|exists:categories,id',
        ], $this->categoryMessages());
        $this->assertCategoryScope($request);

        $customPage = DB::transaction(function () use ($data, $request) {
            $customPage = CustomPage::create([
                'name_en' => $data['name_en'],
                'name_ar' => $data['name_ar'],
                'description_en' => $data['description_en'] ?? null,
                'description_ar' => $data['description_ar'] ?? null,
                'has_filters' => $request->boolean('has_filters'),
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'seo_title_en' => $data['seo_title_en'] ?? null,
                'seo_title_ar' => $data['seo_title_ar'] ?? null,
                'seo_description_en' => $data['seo_description_en'] ?? null,
                'seo_description_ar' => $data['seo_description_ar'] ?? null,
                'listing_types' => $this->service->normalizeListingTypes($data['listing_types'] ?? null),
                'all_categories' => $request->boolean('all_categories'),
            ]);

            $slug = $this->service->uniqueSlug($data['slug'] ?? null, $data['name_en'], $customPage);
            Slug::upsertFor($customPage, $slug);

            $this->service->syncCategories(
                $customPage,
                $request->boolean('all_categories') ? [] : ($data['category_ids'] ?? [])
            );

            return $customPage;
        });

        return response()->json([
            'success' => true,
            'redirect' => route('admin.custom-pages.edit', $customPage->id),
            'message' => __('admin.custom_pages.created'),
        ]);
    }

    public function edit(string $customPage): View
    {
        $customPageModel = CustomPage::withoutTrashed()
            ->with(['slugRecord', 'primaryImage', 'categories'])
            ->findOrFail($customPage);

        return view('admin.custom-pages.edit', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.custom_pages'), 'url' => route('admin.custom-pages.index')],
                ['label' => e($customPageModel->name_en)],
            ],
            'customPage' => $customPageModel,
            'filterableAttributes' => $this->service->filterableAttributes($customPageModel),
        ]);
    }

    public function update(Request $request, string $customPage): JsonResponse
    {
        $customPageModel = CustomPage::withoutTrashed()->findOrFail($customPage);

        $data = $request->validate([
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'has_filters' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'seo_title_en' => 'nullable|string|max:255',
            'seo_title_ar' => 'nullable|string|max:255',
            'seo_description_en' => 'nullable|string|max:255',
            'seo_description_ar' => 'nullable|string|max:255',
            'listing_types' => 'nullable|array',
            'listing_types.*' => ['string', \Illuminate\Validation\Rule::in(CustomPage::LISTING_TYPES)],
            'all_categories' => 'nullable|boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|exists:categories,id',
        ], $this->categoryMessages());
        $this->assertCategoryScope($request);

        DB::transaction(function () use ($data, $request, $customPageModel) {
            $customPageModel->update([
                'name_en' => $data['name_en'],
                'name_ar' => $data['name_ar'],
                'description_en' => $data['description_en'] ?? null,
                'description_ar' => $data['description_ar'] ?? null,
                'has_filters' => $request->boolean('has_filters'),
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'seo_title_en' => $data['seo_title_en'] ?? null,
                'seo_title_ar' => $data['seo_title_ar'] ?? null,
                'seo_description_en' => $data['seo_description_en'] ?? null,
                'seo_description_ar' => $data['seo_description_ar'] ?? null,
                'listing_types' => $this->service->normalizeListingTypes($data['listing_types'] ?? null),
                'all_categories' => $request->boolean('all_categories'),
            ]);

            $currentSlug = $customPageModel->slugRecord?->slug_url;
            if (!empty($data['slug']) && $data['slug'] !== $currentSlug) {
                $slug = $this->service->uniqueSlug($data['slug'], $data['name_en'], $customPageModel);
                Slug::upsertFor($customPageModel, $slug);
            }

            $this->service->syncCategories(
                $customPageModel,
                $request->boolean('all_categories') ? [] : ($data['category_ids'] ?? [])
            );
        });

        return response()->json(['success' => true, 'message' => __('admin.custom_pages.updated')]);
    }

    private function categoryMessages(): array
    {
        return ['category_ids.required_without_all' => __('admin.custom_pages.categories_required')];
    }

    private function assertCategoryScope(Request $request): void
    {
        if (!$request->boolean('all_categories') && empty($request->input('category_ids'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'category_ids' => [__('admin.custom_pages.categories_required')],
            ]);
        }
    }

    public function destroy(string $customPage): JsonResponse
    {
        $customPageModel = CustomPage::withoutTrashed()->findOrFail($customPage);
        $customPageModel->delete();

        return response()->json(['success' => true, 'message' => __('admin.custom_pages.deleted')]);
    }

    public function toggleActive(string $customPage): JsonResponse
    {
        $customPageModel = CustomPage::withoutTrashed()->findOrFail($customPage);
        $customPageModel->update(['is_active' => !$customPageModel->is_active]);

        return response()->json(['success' => true, 'is_active' => (bool) $customPageModel->fresh()->is_active]);
    }

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

    public function syncCategories(Request $request, string $customPage): JsonResponse
    {
        $customPageModel = CustomPage::withoutTrashed()->findOrFail($customPage);

        $request->validate([
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|exists:categories,id',
        ]);

        $this->service->syncCategories($customPageModel, $request->input('category_ids', []));

        return response()->json([
            'success' => true,
            'categories' => $customPageModel->categories()->get(['categories.id', 'categories.name_en', 'categories.name_ar', 'categories.slug']),
        ]);
    }

    public function uploadImage(Request $request, string $customPage): JsonResponse
    {
        $customPageModel = CustomPage::withoutTrashed()->findOrFail($customPage);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:4096'],
        ]);

        DB::beginTransaction();

        $existing = $customPageModel->primaryImage()->first();
        if ($existing) {
            Storage::disk($existing->storage_type ?: 'public')->delete($existing->path);
            $existing->delete();
        }

        $uploadedFile = $request->file('image');
        $ext = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->guessExtension();
        $path = $uploadedFile->storeAs(
            'custom-pages/' . $customPageModel->id,
            'image_' . Str::random(8) . '.' . $ext,
            'public'
        );

        $file = File::create([
            'key' => 'custom-pages/' . $customPageModel->id . '/image',
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'custom_page_image',
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $ext,
            'size' => $uploadedFile->getSize(),
            'model_type' => CustomPage::class,
            'model_id' => $customPageModel->id,
            'is_primary' => 1,
            'position' => 0,
        ]);

        DB::commit();

        return response()->json([
            'message' => __('admin.custom_pages.image_uploaded'),
            'file_id' => $file->id,
            'url' => $file->full_path,
        ]);
    }
}
