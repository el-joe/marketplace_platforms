<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Country;
use App\Models\WarrantyPlan;
use App\Models\WarrantyPurchase;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class WarrantyPlanController extends Controller
{
    use HasDataTable;

    private const CURRENCIES = ['AED', 'SAR', 'EGP', 'KWD', 'OMR', 'QAR', 'BHD', 'JOD'];

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.warranty-plans.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.warranty_plans')],
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatableData(Request $request): JsonResponse
    {
        $columns = [
            ['title' => 'ID', 'data' => 'id', 'name' => 'id', 'orderable_column' => 'warranty_plans.id'],
            ['title' => 'Name (EN)', 'data' => 'name_en', 'name' => 'name_en', 'orderable_column' => 'warranty_plans.name_en', 'searchable_columns' => ['warranty_plans.name_en']],
            ['title' => 'Category', 'data' => 'category_path', 'name' => 'category_path', 'orderable' => false, 'searchable' => false],
            ['title' => 'Duration', 'data' => 'duration_months', 'name' => 'duration_months', 'orderable_column' => 'warranty_plans.duration_months', 'searchable' => false],
            ['title' => 'Price', 'data' => 'price_display', 'name' => 'price_display', 'orderable_column' => 'warranty_plans.price', 'searchable' => false],
            ['title' => 'Active', 'data' => 'is_active', 'name' => 'is_active', 'orderable_column' => 'warranty_plans.is_active', 'searchable' => false],
            ['title' => 'Sort Order', 'data' => 'sort_order', 'name' => 'sort_order', 'orderable_column' => 'warranty_plans.sort_order', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];

        $query = WarrantyPlan::query()
            ->select([
                'warranty_plans.id',
                'warranty_plans.name_en',
                'warranty_plans.category_id',
                'warranty_plans.duration_months',
                'warranty_plans.price',
                'warranty_plans.currency',
                'warranty_plans.is_active',
                'warranty_plans.sort_order',
            ])
            ->with(['category.parent.parent.parent.parent']);

        $query = $this->applyFilters($query, $request, [
            'category_id' => fn($q, $v) => $q->where('warranty_plans.category_id', $v),
            'is_active' => fn($q, $v) => $q->where('warranty_plans.is_active', (bool) $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function (WarrantyPlan $plan) {
            return [
                'id' => $plan->id,
                'name_en' => e($plan->name_en),
                'category_path' => e($this->categoryBreadcrumb($plan->category)),
                'duration_months' => (int) $plan->duration_months,
                'price_display' => number_format($plan->price, 2) . ' ' . e($plan->currency),
                'is_active' => (bool) $plan->is_active,
                'sort_order' => (int) $plan->sort_order,
                'edit_url' => route('admin.warranty-plans.edit', $plan->id),
                'toggle_url' => route('admin.warranty-plans.toggle', $plan->id),
                'delete_url' => route('admin.warranty-plans.destroy', $plan->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.warranty-plans.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.warranty_plans'), 'url' => route('admin.warranty-plans.index')],
                ['label' => __('admin.warranty_plans.new_plan')],
            ],
            'categories' => $this->categoryOptions(),
            'countries' => Country::query()->orderBy('name_en')->get(['id', 'name_en', 'name_ar']),
            'currencies' => self::CURRENCIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(true));

        $plan = new WarrantyPlan($this->planData($request, $validated));
        $plan->created_by_admin_id = auth('admin')->id();
        $plan->save();

        return redirect()->route('admin.warranty-plans.index')
            ->with('success', __('admin.warranty_plans.created_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $warrantyPlan): View
    {
        $plan = WarrantyPlan::findOrFail($warrantyPlan);

        return view('admin.warranty-plans.edit', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.warranty_plans'), 'url' => route('admin.warranty-plans.index')],
                ['label' => e($plan->name_en)],
            ],
            'plan' => $plan,
            'categories' => $this->categoryOptions(),
            'countries' => Country::query()->orderBy('name_en')->get(['id', 'name_en', 'name_ar']),
            'currencies' => self::CURRENCIES,
        ]);
    }

    public function update(Request $request, string $warrantyPlan): RedirectResponse
    {
        $plan = WarrantyPlan::findOrFail($warrantyPlan);

        $rules = $this->rules(false);
        $rules['category_id'] = 'nullable|uuid|exists:categories,id';

        $validated = $request->validate($rules);

        $plan->update($this->planData($request, $validated, $plan));

        return redirect()->route('admin.warranty-plans.edit', $plan->id)
            ->with('success', __('admin.warranty_plans.updated_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Active
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleActive(string $warrantyPlan): JsonResponse
    {
        $plan = WarrantyPlan::findOrFail($warrantyPlan);
        $plan->is_active = !$plan->is_active;
        $plan->save();

        return response()->json(['success' => true, 'is_active' => $plan->is_active]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $warrantyPlan): RedirectResponse
    {
        $plan = WarrantyPlan::findOrFail($warrantyPlan);

        $purchaseCount = WarrantyPurchase::query()->where('warranty_plan_id', $plan->id)->count();

        if ($purchaseCount > 0) {
            return back()->withErrors([
                'error' => __('admin.warranty_plans.cannot_delete_has_purchases'),
            ]);
        }

        $plan->delete();

        return redirect()->route('admin.warranty-plans.index')
            ->with('success', __('admin.warranty_plans.deleted_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function rules(bool $categoryRequired): array
    {
        return [
            'category_id' => ($categoryRequired ? 'required' : 'nullable') . '|uuid|exists:categories,id',
            'name_en' => 'required|string|max:150',
            'name_ar' => 'required|string|max:150',
            'duration_months' => 'required|integer|min:1|max:120',
            'price_type' => 'nullable|in:flat,percentage',
            'price' => 'required_if:price_type,flat|nullable|numeric|min:0.01',
            'price_pct' => 'required_if:price_type,percentage|nullable|numeric|min:0.01|max:100',
            'currency' => 'required|string|size:3|in:' . implode(',', self::CURRENCIES),
            'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:2048',
            'features_en' => 'required|array|min:1|max:8',
            'features_en.*' => 'required|string|max:200',
            'features_ar' => 'required|array|min:1|max:8',
            'features_ar.*' => 'required|string|max:200',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'uuid|exists:countries,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function planData(Request $request, array $validated, ?WarrantyPlan $existing = null): array
    {
        $priceType = $validated['price_type'] ?? 'flat';

        $data = [
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'duration_months' => (int) $validated['duration_months'],
            'price_type' => $priceType,
            'currency' => strtoupper($validated['currency']),
            'features_en' => array_values($validated['features_en']),
            'features_ar' => array_values($validated['features_ar']),
            'country_ids' => !empty($validated['country_ids']) ? array_values($validated['country_ids']) : null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if ($priceType === 'percentage') {
            $data['price'] = 0;
            $data['price_pct'] = (float) $validated['price_pct'];
        } else {
            $data['price'] = (int) round((float) $validated['price']);
            $data['price_pct'] = null;
        }

        if (array_key_exists('category_id', $validated) && $validated['category_id']) {
            $data['category_id'] = $validated['category_id'];
        }

        if ($request->hasFile('image')) {
            if ($existing && $existing->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            $data['image_path'] = $request->file('image')->store('warranty-plans', 'public');
        }

        return $data;
    }

    private function categoryOptions()
    {
        return Category::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('lft')
            ->get(['id', 'name_en', 'depth']);
    }

    private function categoryBreadcrumb(?Category $category): string
    {
        if (!$category) {
            return '';
        }

        $names = $category->ancestors()->get(['name_en'])->pluck('name_en')->push($category->name_en);

        return $names->implode(' / ');
    }
}
