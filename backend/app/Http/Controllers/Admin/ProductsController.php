<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductsController extends Controller
{
    use HasDataTable;

    /**
     * Column definitions shared between the DataTables endpoint and the view.
     * Each entry maps directly to a DataTables column config on the JS side.
     *
     * Keys used server-side:
     *   orderable_column    → used in ORDER BY
     *   searchable_columns  → used in WHERE LIKE global search
     */
    private function columnDefinitions(): array
    {
        return [
            0 => [
                'title' => 'Product',
                'data' => 'name',
                'name' => 'name',
                'searchable_columns' => ['products.name', 'products.sku'],
                'orderable_column' => 'products.name',
            ],
            1 => [
                'title' => 'SKU',
                'data' => 'sku',
                'name' => 'sku',
                'orderable_column' => 'products.sku',
                'className' => 'font-mono text-xs',
            ],
            2 => [
                'title' => 'Category',
                'data' => 'category_name',
                'name' => 'category',
                'orderable_column' => 'categories.name',
            ],
            3 => [
                'title' => 'Vendor',
                'data' => 'vendor_name',
                'name' => 'vendor',
                'orderable_column' => 'vendors.business_name',
            ],
            4 => [
                'title' => 'Price',
                'data' => 'price',
                'name' => 'price',
                'orderable_column' => 'products.price',
                'className' => 'text-right',
            ],
            5 => [
                'title' => 'Stock',
                'data' => 'stock_quantity',
                'name' => 'stock',
                'orderable_column' => 'products.stock_quantity',
                'className' => 'text-right',
            ],
            6 => [
                'title' => 'Status',
                'data' => 'status',
                'name' => 'status',
                'orderable_column' => 'products.status',
            ],
            7 => [
                'title' => 'Created',
                'data' => 'created_at',
                'name' => 'created_at',
                'orderable_column' => 'products.created_at',
            ],
            8 => [
                'title' => 'Actions',
                'data' => 'id',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-right',
            ],
        ];
    }

    public function index(): View
    {
        return view('admin.products.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Products', 'url' => ''],
            ],
        ]);
    }

    /**
     * DataTables server-side endpoint.
     * Route: POST /admin/products/datatable
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = Product::query()
            ->select([
                'products.*',
                'categories.name as category_name',
                'vendors.business_name as vendor_name',
            ])
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'products.vendor_id');

        // ── Filter scopes ─────────────────────────────────────────────────
        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('products.status', $v),
            'category_id' => fn($q, $v) => $q->where('products.category_id', $v),
            'vendor_id' => fn($q, $v) => $q->where('products.vendor_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('products.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('products.created_at', '<=', $v),
            'price_min' => fn($q, $v) => $q->where('products.price', '>=', (int) round((float) $v)),
            'price_max' => fn($q, $v) => $q->where('products.price', '<=', (int) round((float) $v)),
        ]);

        return $this->dataTableResponse(
            $request,
            $query,
            $this->columnDefinitions(),
            function ($product) {
                return [
                    'id' => $product->id,
                    'name' => e($product->name),
                    'sku' => e($product->sku),
                    'category_name' => e($product->category_name ?? '—'),
                    'vendor_name' => e($product->vendor_name ?? '—'),
                    'price' => $product->price,           // raw cents, rendered JS-side
                    'stock_quantity' => $product->stock_quantity ?? 0,
                    'status' => $product->status?->value,
                    'created_at' => $product->created_at?->toISOString(),
                    // Pre-built action URLs for JS renderer
                    'edit_url' => route('admin.products.edit', $product->id),
                ];
            }
        );
    }

    /**
     * Bulk actions endpoint.
     * Route: POST /admin/products/bulk
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete,activate,deactivate'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $count = 0;

        match ($validated['action']) {
            'delete' => $count = Product::whereIn('id', $validated['ids'])->delete(),

            'activate' => $count = Product::whereIn('id', $validated['ids'])
                ->update(['status' => 'active']),

            'deactivate' => $count = Product::whereIn('id', $validated['ids'])
                ->update(['status' => 'inactive']),
        };

        return response()->json([
            'message' => "{$count} product(s) updated successfully.",
        ]);
    }
}
