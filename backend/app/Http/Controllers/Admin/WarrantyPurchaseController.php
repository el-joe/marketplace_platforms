<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarrantyPurchase;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarrantyPurchaseController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_plans.view'), 403);

        $stats = [
            'active' => WarrantyPurchase::where('status', 'active')->count(),
            'pending' => WarrantyPurchase::where('status', 'pending')->count(),
            'expired' => WarrantyPurchase::where('status', 'expired')->count(),
            'cancelled' => WarrantyPurchase::where('status', 'cancelled')->count(),
        ];

        $plans = \App\Models\WarrantyPlan::orderBy('name_en')->get(['id', 'name_en']);
        $categories = \App\Models\Category::orderBy('name_en')->get(['id', 'name_en']);

        return view('admin.warranty-purchases.index', compact('stats', 'plans', 'categories'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatableData(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_plans.view'), 403);

        $query = WarrantyPurchase::query()
            ->leftJoin('customers', 'customers.id', '=', 'warranty_purchases.customer_id')
            ->leftJoin('orders', 'orders.id', '=', 'warranty_purchases.order_id')
            ->leftJoin('warranty_plans', 'warranty_plans.id', '=', 'warranty_purchases.warranty_plan_id')
            ->leftJoin('order_items', 'order_items.id', '=', 'warranty_purchases.order_item_id')
            ->select(
                'warranty_purchases.*',
                'customers.name as customer_name',
                'orders.order_number as order_number',
                'warranty_plans.name_en as plan_name',
                'warranty_plans.category_id as plan_category_id',
                'order_items.product_snapshot as order_item_product_snapshot'
            );

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('warranty_purchases.status', $v),
            'plan_id' => fn($q, $v) => $q->where('warranty_purchases.warranty_plan_id', $v),
            'category_id' => fn($q, $v) => $q->where('warranty_plans.category_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('warranty_purchases.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('warranty_purchases.created_at', '<=', $v),
        ]);

        $columns = [
            0 => ['searchable_columns' => ['warranty_purchases.id'], 'orderable_column' => 'warranty_purchases.id'],
            1 => ['searchable_columns' => ['customers.name']],
            2 => [],
            3 => ['searchable_columns' => ['warranty_plans.name_en']],
            4 => [],
            5 => ['orderable_column' => 'warranty_purchases.price_paid'],
            6 => ['orderable_column' => 'warranty_purchases.status'],
            7 => ['orderable_column' => 'warranty_purchases.coverage_starts_at'],
            8 => ['orderable_column' => 'warranty_purchases.coverage_ends_at'],
            9 => ['orderable_column' => 'warranty_purchases.created_at'],
            10 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($w) {
            $statusBadge = $this->statusBadgeClass($w->status);
            $planSnapshot = is_array($w->plan_snapshot) ? $w->plan_snapshot : [];
            $productSnapshot = json_decode($w->order_item_product_snapshot ?? '', true) ?: [];
            $productName = $productSnapshot['name_en'] ?? $productSnapshot['name'] ?? null;
            $planName = $planSnapshot['name_en'] ?? $w->plan_name;
            $duration = $planSnapshot['duration_label'] ?? ($planSnapshot['duration_months'] ?? null);
            $showUrl = route('admin.warranty-purchases.show', $w->id);

            return [
                'DT_RowId' => 'wp-' . $w->id,
                'purchase_id' => '<a href="' . $showUrl . '" class="font-mono text-xs text-primary-600 hover:underline">' . e(substr($w->id, 0, 8)) . '…</a>',
                'customer' => '<span class="text-sm text-gray-700">' . e($w->customer_name ?? '—') . '</span>',
                'product' => '<span class="text-sm text-gray-700">' . e($productName ?? '—') . '</span>',
                'plan' => '<span class="text-sm text-gray-700">' . e($planName ?? '—') . '</span>',
                'duration' => '<span class="text-sm text-gray-500">' . e($duration ?? '—') . '</span>',
                'price' => '<span class="text-sm text-gray-700 whitespace-nowrap">' . number_format($w->price_paid / 100, 2) . ' ' . e($w->currency) . '</span>',
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e(ucfirst($w->status)) . '</span>',
                'coverage_starts_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . ($w->coverage_starts_at ? $w->coverage_starts_at->format('M d, Y') : '—') . '</span>',
                'coverage_ends_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . ($w->coverage_ends_at ? $w->coverage_ends_at->format('M d, Y') : '—') . '</span>',
                'created_at' => '<span class="text-xs text-gray-500 whitespace-nowrap">' . \Carbon\Carbon::parse($w->created_at)->format('M d, Y H:i') . '</span>',
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-secondary">' . e(__('common.view')) . '</a>',
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(WarrantyPurchase $warrantyPurchase): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_plans.view'), 403);

        $warrantyPurchase->load([
            'customer:id,name,email',
            'order:id,order_number',
            'plan:id,name_en,name_ar',
            'orderItem:id,order_id,product_snapshot,sku,quantity,unit_price,warranty_purchase_id',
        ]);

        $relatedClaim = null;
        if ($warrantyPurchase->orderItem) {
            $relatedClaim = \App\Models\WarrantyClaim::where('order_item_id', $warrantyPurchase->orderItem->id)->first();
        }

        return view('admin.warranty-purchases.show', compact('warrantyPurchase', 'relatedClaim'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Summary
    // ─────────────────────────────────────────────────────────────────────────

    public function summary(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warranty_plans.view'), 403);

        $revenueByCurrency = WarrantyPurchase::query()
            ->select('currency', DB::raw('SUM(price_paid) as total'))
            ->groupBy('currency')
            ->get();

        $stats = [
            'active' => WarrantyPurchase::where('status', 'active')->count(),
            'pending' => WarrantyPurchase::where('status', 'pending')->count(),
            'expired' => WarrantyPurchase::where('status', 'expired')->count(),
        ];

        $monthlyRevenue = WarrantyPurchase::query()
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(price_paid) as total')
            )
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $revenueByPlan = WarrantyPurchase::query()
            ->leftJoin('warranty_plans', 'warranty_plans.id', '=', 'warranty_purchases.warranty_plan_id')
            ->select(
                'warranty_plans.id as plan_id',
                'warranty_plans.name_en as plan_name',
                DB::raw('COUNT(warranty_purchases.id) as sales_count'),
                DB::raw('SUM(warranty_purchases.price_paid) as total')
            )
            ->groupBy('warranty_plans.id', 'warranty_plans.name_en')
            ->orderByDesc('total')
            ->get();

        return view('admin.warranty-purchases.summary', compact(
            'revenueByCurrency',
            'stats',
            'monthlyRevenue',
            'revenueByPlan'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-100 text-green-700',
            'pending' => 'bg-yellow-100 text-yellow-700',
            'expired' => 'bg-gray-100 text-gray-500',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
