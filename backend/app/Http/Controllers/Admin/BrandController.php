<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Services\BrandService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BrandController extends Controller
{
    use HasDataTable;

    public function __construct(private readonly BrandService $service)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.brands.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.brands')],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();

        $query = Brand::query()
            ->select([
                'brands.id',
                'brands.name_en',
                'brands.name_ar',
                'brands.slug',
                'brands.is_verified',
                'brands.is_restricted',
                'brands.is_active',
                'brands.created_at',
            ])
            ->selectSub(
                Product::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('products.brand_id', 'brands.id')
                    ->whereNull('products.deleted_at'),
                'product_count'
            )
            ->whereNull('brands.deleted_at');

        $query = $this->applyFilters($query, $request, [
            'is_verified' => fn($q, $v) => $q->where('brands.is_verified', (bool) $v),
            'is_restricted' => fn($q, $v) => $q->where('brands.is_restricted', (bool) $v),
            'is_active' => fn($q, $v) => $q->where('brands.is_active', (bool) $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'name_en' => e($row->name_en),
                'name_ar' => e($row->name_ar ?? ''),
                'slug' => e($row->slug),
                'is_verified' => (bool) $row->is_verified,
                'is_restricted' => (bool) $row->is_restricted,
                'is_active' => (bool) $row->is_active,
                'product_count' => (int) $row->product_count,
                'created_at' => $row->created_at,
                'edit_url' => route('admin.brands.edit', $row->id),
                'delete_url' => route('admin.brands.destroy', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.brands.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.brands'), 'url' => route('admin.brands.index')],
                ['label' => __('admin.brands.new_brand')],
            ],
        ]);
    }

    public function store(StoreBrandRequest $request): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();
        try {
            $brand = $this->service->create($request->validated());
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BrandController@store failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => __('admin.brands.create_failed')], 500);
            }
            return back()->withInput()->withErrors(['error' => __('admin.brands.create_failed')]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.brands.edit', $brand->id),
            ]);
        }

        return redirect()->route('admin.brands.edit', $brand->id)
            ->with('success', __('admin.brands.created_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $brand): View
    {
        $model = Brand::findOrFail($brand);

        return view('admin.brands.edit', [
            'brand' => $model,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.brands'), 'url' => route('admin.brands.index')],
                ['label' => e($model->name_en)],
            ],
        ]);
    }

    public function update(UpdateBrandRequest $request, string $brand): JsonResponse
    {
        $model = Brand::findOrFail($brand);

        DB::beginTransaction();
        try {
            $this->service->update($model, $request->validated());
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BrandController@update failed', ['brand' => $brand, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('admin.brands.update_failed')], 500);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $brand): JsonResponse
    {
        $model = Brand::findOrFail($brand);

        $productCount = Product::query()
            ->where('brand_id', $model->id)
            ->whereNull('deleted_at')
            ->count();

        if ($productCount > 0) {
            return response()->json([
                'message' => trans_choice('admin.brands.cannot_delete_has_products', $productCount, ['count' => $productCount]),
            ], 422);
        }

        $model->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Async select search
    // ─────────────────────────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $brands = Brand::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($term) {
                $q->where('name_en', 'like', "%{$term}%")
                    ->orWhere('name_ar', 'like', "%{$term}%");
            })
            ->orderBy('name_en')
            ->limit(30)
            ->get(['id', 'name_en', 'name_ar']);

        return response()->json([
            'results' => $brands->map(fn($b) => [
                'id' => $b->id,
                'text' => $b->name_en . ($b->name_ar ? " / {$b->name_ar}" : ''),
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────

    private function columnDefinitions(): array
    {
        return [
            ['title' => 'Name (EN)', 'data' => 'name_en', 'name' => 'name_en', 'orderable_column' => 'brands.name_en'],
            ['title' => 'Name (AR)', 'data' => 'name_ar', 'name' => 'name_ar', 'orderable_column' => 'brands.name_ar', 'searchable' => false],
            ['title' => 'Verified', 'data' => 'is_verified', 'name' => 'is_verified', 'orderable_column' => 'brands.is_verified', 'searchable' => false],
            ['title' => 'Restricted', 'data' => 'is_restricted', 'name' => 'is_restricted', 'orderable_column' => 'brands.is_restricted', 'searchable' => false],
            ['title' => 'Active', 'data' => 'is_active', 'name' => 'is_active', 'orderable_column' => 'brands.is_active', 'searchable' => false],
            ['title' => 'Products', 'data' => 'product_count', 'name' => 'product_count', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ['title' => 'Created', 'data' => 'created_at', 'name' => 'created_at', 'orderable_column' => 'brands.created_at', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
        ];
    }
}
