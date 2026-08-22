<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FbtController extends Controller
{
    use HasDataTable;

    public function index(): View
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', Product::class);

        return view('admin.fbt.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.product_relations')],
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::forUser(Auth::guard('admin')->user())->authorize('viewAny', Product::class);

        $columns = [
            ['title' => __('admin.product_relations_section.product'), 'data' => 'name_en', 'name' => 'name_en', 'orderable_column' => 'products.name_en', 'searchable_columns' => ['products.name_en', 'product_variants.sku']],
            ['title' => __('admin.product_relations_section.sku'), 'data' => 'sku', 'name' => 'sku', 'searchable' => false],
            ['title' => __('admin.product_relations_section.related_count'), 'data' => 'related_count', 'name' => 'related_count', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        $query = Product::query()
            ->withCount('frequentlyBoughtTogether')
            ->leftJoin('product_variants', function ($join) {
                $join->on('product_variants.product_id', '=', 'products.id')
                    ->where('product_variants.is_default', true)
                    ->whereNull('product_variants.deleted_at');
            })
            ->select(['products.id', 'products.name_en', 'product_variants.sku']);

        $query = $this->applyFilters($query, $request, [
            'has_relations' => fn ($q, $v) => $v === '1'
                ? $q->has('frequentlyBoughtTogether')
                : $q->doesntHave('frequentlyBoughtTogether'),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'name_en' => e($row->name_en),
                'sku' => e($row->sku),
                'related_count' => (int) $row->frequently_bought_together_count,
                'manage_url' => route('admin.products.edit', $row->id),
            ];
        });
    }
}
