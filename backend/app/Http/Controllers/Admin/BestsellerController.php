<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Country;
use App\Models\ProductBestsellerRanking;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BestsellerController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.products.bestsellers', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.products'), 'url' => route('admin.products.index')],
                ['label' => __('admin.bestsellers.title')],
            ],
            'categories' => Category::query()->orderBy('name_en')->get(['id', 'name_en']),
            'countries' => Country::query()->orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['title' => 'ID', 'data' => 'id', 'name' => 'id', 'orderable_column' => 'product_bestseller_rankings.id'],
            ['title' => 'Rank', 'data' => 'rank', 'name' => 'rank', 'orderable_column' => 'product_bestseller_rankings.rank', 'searchable' => false],
            ['title' => 'Product', 'data' => 'product_name', 'name' => 'product_name', 'orderable' => false, 'searchable_columns' => ['products.name_en']],
            ['title' => 'Category', 'data' => 'category_name', 'name' => 'category_name', 'orderable' => false, 'searchable' => false],
            ['title' => 'Country', 'data' => 'country_name', 'name' => 'country_name', 'orderable' => false, 'searchable' => false],
            ['title' => 'Total Sold', 'data' => 'units_sold', 'name' => 'units_sold', 'orderable_column' => 'product_bestseller_rankings.units_sold', 'searchable' => false],
            ['title' => 'Ranked At', 'data' => 'calculated_at', 'name' => 'calculated_at', 'orderable_column' => 'product_bestseller_rankings.calculated_at', 'searchable' => false],
        ];

        $query = ProductBestsellerRanking::query()
            ->select([
                'product_bestseller_rankings.id',
                'product_bestseller_rankings.product_id',
                'product_bestseller_rankings.category_id',
                'product_bestseller_rankings.country_id',
                'product_bestseller_rankings.rank',
                'product_bestseller_rankings.units_sold',
                'product_bestseller_rankings.period',
                'product_bestseller_rankings.calculated_at',
            ])
            ->join('products', 'products.id', '=', 'product_bestseller_rankings.product_id')
            ->with(['product:id,name_en', 'category:id,name_en', 'country:id,name_en']);

        $query = $this->applyFilters($query, $request, [
            'category_id' => fn($q, $v) => $q->where('product_bestseller_rankings.category_id', $v),
            'country_id' => fn($q, $v) => $q->where('product_bestseller_rankings.country_id', $v),
            'period' => fn($q, $v) => $q->where('product_bestseller_rankings.period', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function (ProductBestsellerRanking $ranking) {
            return [
                'id' => $ranking->id,
                'rank' => (int) $ranking->rank,
                'product_name' => e(optional($ranking->product)->name_en),
                'category_name' => e(optional($ranking->category)->name_en),
                'country_name' => e(optional($ranking->country)->name_en),
                'units_sold' => (int) $ranking->units_sold,
                'calculated_at' => optional($ranking->calculated_at)->format('Y-m-d H:i'),
            ];
        });
    }
}
