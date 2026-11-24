<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryShippingMethod;
use App\Models\Country;
use App\Models\CountryCategory;
use App\Models\Page;
use App\Models\ShippingMethod;
use App\Services\CategoryCountryAvailabilityService;
use App\Services\CategoryFormService;
use App\Services\CategoryService;
use App\Support\Bilingual;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use HasDataTable;

    public function __construct(private CategoryService $service) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Tree
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Async category picker (product form and other admin selects). Matches the
     * term in both languages and labels each result in the panel locale, with
     * the parent name for context ("Parent › Child").
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        $categories = Category::query()
            ->where('is_active', true)
            ->when($term !== '', fn ($query) => Bilingual::whereLike($query, 'name', $term))
            ->with('parent:id,name_en,name_ar')
            ->orderBy(Bilingual::column('name'))
            ->limit(30)
            ->get(['id', 'parent_id', 'name_en', 'name_ar']);

        return response()->json([
            'results' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'text' => $category->parent
                    ? localized($category->parent).' › '.localized($category)
                    : localized($category),
            ])->values(),
        ]);
    }

    /**
     * Variant attributes (with values) of a category, labelled in the panel locale.
     */
    public function variantAttributes(string $id): JsonResponse
    {
        $attributes = DB::table('category_attributes as ca')
            ->join('attributes as a', 'a.id', '=', 'ca.attribute_id')
            ->where('ca.category_id', $id)
            ->where('a.is_variant_attribute', true)
            ->select('a.id', 'a.name_en', 'a.name_ar')
            ->orderBy('a.sort_order')
            ->get();

        $valuesByAttribute = DB::table('attribute_values')
            ->whereIn('attribute_id', $attributes->pluck('id'))
            ->orderBy('sort_order')
            ->get(['id', 'attribute_id', 'value_en', 'value_ar'])
            ->groupBy('attribute_id');

        $data = $attributes->map(fn ($attribute) => [
            'id' => $attribute->id,
            'name' => localized($attribute),
            'name_en' => $attribute->name_en,
            'values' => ($valuesByAttribute->get($attribute->id) ?? collect())
                ->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => localized($value, 'value'),
                    'value_en' => $value->value_en,
                ])
                ->values(),
        ]);

        return response()->json(['data' => $data]);
    }

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
            'homePagesWithoutFeaturedPills' => $this->homePagesWithoutFeaturedPills(),
        ]);
    }

    /**
     * Published country home pages where Featured categories have no visible effect: they have no visible
     * "Category pills" block without hand-picked categories (the only place Featured feeds). Read-only.
     * Empty when no category is featured, so the hint only appears when Featured is actually in use.
     *
     * @return Collection<int, Page>
     */
    private function homePagesWithoutFeaturedPills(): Collection
    {
        if (! Category::query()->where('is_featured', true)->exists()) {
            return collect();
        }

        return Page::query()
            ->published()
            ->where('page_type', 'home')
            ->whereNull('app_context_key')
            ->whereDoesntHave('blocks', fn ($blocks) => $blocks
                ->where('block_type', 'category_pills')
                ->where('is_visible', true)
                ->whereDoesntHave('blockCategories'))
            ->with('country:id,name_en,name_ar')
            ->get(['id', 'country_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.categories.create', array_merge($this->formData(null), [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.categories'), 'url' => route('admin.categories.index')],
                ['label' => __('admin.categories.new_category')],
            ],
        ]));
    }

    /**
     * Creates the category with every section of the form (attributes, shipping,
     * commissions, tiers, countries, image…) in one transaction.
     */
    public function store(StoreCategoryRequest $request, CategoryFormService $form): JsonResponse|RedirectResponse
    {
        $result = $form->save(null, $this->formInput($request), $request->file('image'), auth('admin')->id());

        $warning = $this->contractGapWarning($result['contract_gaps']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.categories.category_created'),
                'redirect' => route('admin.categories.edit', $result['category']->id),
                'warning' => $warning,
            ]);
        }

        return redirect()->route('admin.categories.edit', $result['category']->id)
            ->with('success', __('admin.categories.category_created'))
            ->with('warning', $warning);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $category): View
    {
        $categoryModel = Category::with(['categoryAttributes.attribute', 'primaryImage'])
            ->whereNull('deleted_at')
            ->findOrFail($category);

        return view('admin.categories.edit', array_merge($this->formData($categoryModel), [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.categories'), 'url' => route('admin.categories.index')],
                ['label' => localized($categoryModel)],
            ],
            'category' => $categoryModel,
        ]));
    }

    /**
     * "Save all": stores every section of the edit form in one transaction.
     */
    public function update(UpdateCategoryRequest $request, string $category, CategoryFormService $form): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $result = $form->save($categoryModel, $this->formInput($request), $request->file('image'), auth('admin')->id());

        return response()->json([
            'success' => true,
            'message' => __('admin.categories.category_updated'),
            'warning' => $this->contractGapWarning($result['contract_gaps']),
            'image_url' => $result['category']->image_url,
        ]);
    }

    /**
     * Validated fields plus the raw contract-partial fields (their rules come
     * from ContractAssignmentService and it reads them from the raw input).
     *
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        return array_merge(
            $request->except(['image', '_token', '_method']),
            $request->safe()->except(['image']),
        );
    }

    private function contractGapWarning(Collection $gaps): ?string
    {
        return $gaps->isNotEmpty()
            ? __('contracts.admin.gap_warning', ['countries' => $gaps->map(fn ($country) => localized($country))->implode(', ')])
            : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image Upload
    // ─────────────────────────────────────────────────────────────────────────

    public function uploadImage(Request $request, string $category, CategoryFormService $form): JsonResponse
    {
        abort_unless(auth('admin')->user()->can('categories.edit'), 403);

        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $file = DB::transaction(fn () => $form->storeImage($categoryModel, $request->file('image')));

        return response()->json([
            'message' => __('admin.categories.image_uploaded'),
            'file_id' => $file->id,
            'url' => $file->full_path,
        ]);
    }

    public function deleteImage(Request $request, string $category, CategoryFormService $form): JsonResponse
    {
        abort_unless(auth('admin')->user()->can('categories.edit'), 403);

        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        if (! $form->deleteImage($categoryModel)) {
            return response()->json(['message' => __('admin.categories.image_not_found')], 404);
        }

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
            $this->service->setFeatured($categoryModel, ! $categoryModel->is_featured, auth('admin')->id());

            return response()->json(['success' => true, 'is_featured' => $categoryModel->fresh()->is_featured]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Visible
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleVisible(string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        $this->service->setVisible($categoryModel, ! $categoryModel->is_visible, auth('admin')->id());

        return response()->json([
            'success' => true,
            'is_visible' => (bool) $categoryModel->fresh()->is_visible,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Show in Footer (parent categories only)
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleFooterVisible(string $category): JsonResponse
    {
        $categoryModel = Category::whereNull('deleted_at')->findOrFail($category);

        if ($categoryModel->parent_id !== null) {
            return response()->json(['message' => 'Only parent categories can be toggled in the footer.'], 422);
        }

        $this->service->setShowInFooter($categoryModel, ! $categoryModel->show_in_footer, auth('admin')->id());

        return response()->json([
            'success' => true,
            'show_in_footer' => (bool) $categoryModel->fresh()->show_in_footer,
        ]);
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
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Everything the single category form needs, for create ($category = null) and edit.
     *
     * @return array<string, mixed>
     */
    private function formData(?Category $category): array
    {
        $parents = Category::whereNull('deleted_at')
            ->when($category, fn ($q) => $q->whereNotIn('id', $category->descendants()->pluck('id')->push($category->id)))
            ->orderBy('lft')
            ->get(['id', 'name_en', 'name_ar', 'depth'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => str_repeat('— ', $c->depth ?? 0).localized($c),
            ]);

        $allAttributes = Attribute::query()
            ->orderBy(Bilingual::column('name'))
            ->get(['id', 'name_en', 'name_ar', 'code', 'type']);

        $countries = Country::query()->orderBy(Bilingual::column('name'))->get();
        $activeCountries = $countries->where('is_active', true)->values();

        $availability = app(CategoryCountryAvailabilityService::class);

        $shippingMethods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('display_priority')
            ->get(['id', 'name', 'code']);

        $assignedShipping = $category
            ? CategoryShippingMethod::query()
                ->where('category_id', $category->id)
                ->orderBy('display_priority')
                ->orderBy('created_at')
                ->get()
            : collect();

        return [
            'parents' => $parents,
            'allAttributes' => $allAttributes,
            'countries' => $countries,
            'activeCountries' => $activeCountries,
            'countryCommissions' => $category
                ? CountryCategory::where('category_id', $category->id)->get()->keyBy('country_id')
                : collect(),
            'commissionTiers' => $category
                ? $category->commissionTiers()->orderBy('country_id')->orderBy('sort_order')->get()
                : collect(),
            'shippingMethods' => $shippingMethods,
            'assignedShipping' => $assignedShipping,
            'availability' => $availability->describe($category),
            'inheritedUnavailable' => $category ? $availability->inheritedUnavailableCountryIds($category)->all() : [],
        ];
    }
}
