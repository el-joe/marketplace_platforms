<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductHighlight;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductHighlightController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.products.highlights', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.products'), 'url' => route('admin.products.index')],
                ['label' => __('admin.product_highlights.title')],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['title' => 'ID', 'data' => 'id', 'name' => 'id', 'orderable_column' => 'product_highlights.id'],
            ['title' => 'Product', 'data' => 'product_name', 'name' => 'product_name', 'orderable' => false, 'searchable_columns' => ['products.name_en']],
            ['title' => 'Highlight (EN)', 'data' => 'text_en', 'name' => 'text_en', 'orderable_column' => 'product_highlights.text_en', 'searchable_columns' => ['product_highlights.text_en']],
            ['title' => 'Sort Order', 'data' => 'position', 'name' => 'position', 'orderable_column' => 'product_highlights.position', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];

        $query = ProductHighlight::query()
            ->select([
                'product_highlights.id',
                'product_highlights.product_id',
                'product_highlights.text_en',
                'product_highlights.text_ar',
                'product_highlights.position',
            ])
            ->join('products', 'products.id', '=', 'product_highlights.product_id')
            ->with('product:id,name_en');

        $query = $this->applyFilters($query, $request, [
            'product_id' => fn($q, $v) => $q->where('product_highlights.product_id', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function (ProductHighlight $highlight) {
            return [
                'id' => $highlight->id,
                'product_name' => e(optional($highlight->product)->name_en),
                'text_en' => e($highlight->text_en),
                'position' => (int) $highlight->position,
                'edit_url' => route('admin.product-highlights.edit', $highlight->id),
                'delete_url' => route('admin.product-highlights.destroy', $highlight->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.products.highlight-form', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.products'), 'url' => route('admin.products.index')],
                ['label' => __('admin.product_highlights.title'), 'url' => route('admin.product-highlights.index')],
                ['label' => __('admin.product_highlights.new_highlight')],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        ProductHighlight::create($validated);

        return redirect()->route('admin.product-highlights.index')
            ->with('success', __('admin.product_highlights.created_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(string $productHighlight): View
    {
        $highlight = ProductHighlight::with('product:id,name_en')->findOrFail($productHighlight);

        return view('admin.products.highlight-form', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.products'), 'url' => route('admin.products.index')],
                ['label' => __('admin.product_highlights.title'), 'url' => route('admin.product-highlights.index')],
                ['label' => e($highlight->text_en)],
            ],
            'highlight' => $highlight,
        ]);
    }

    public function update(Request $request, string $productHighlight): RedirectResponse
    {
        $highlight = ProductHighlight::findOrFail($productHighlight);

        $validated = $request->validate($this->rules());

        $highlight->update($validated);

        return redirect()->route('admin.product-highlights.edit', $highlight->id)
            ->with('success', __('admin.product_highlights.updated_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(string $productHighlight): RedirectResponse
    {
        $highlight = ProductHighlight::findOrFail($productHighlight);
        $highlight->delete();

        return redirect()->route('admin.product-highlights.index')
            ->with('success', __('admin.product_highlights.deleted_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Search (for Select2 async product picker)
    // ─────────────────────────────────────────────────────────────────────────

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $rows = Product::query()
            ->whereNull('deleted_at')
            ->when($q !== '', fn($query) => $query->where('name_en', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name_en']);

        return response()->json([
            'results' => $rows->map(fn($p) => ['id' => $p->id, 'text' => $p->name_en])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function rules(): array
    {
        return [
            'product_id' => 'required|uuid|exists:products,id',
            'text_en' => 'required|string|max:500',
            'text_ar' => 'required|string|max:500',
            'position' => 'nullable|integer|min:0',
        ];
    }
}
