<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\ProductImage;
use App\Models\WarehouseInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index — all inventory rows for marketer
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $marketer = $this->marketer();

        // Summary stats
        $stats = WarehouseInventory::join('marketer_listings as ml', 'ml.id', '=', 'warehouse_inventories.marketer_listing_id')
            ->where('ml.marketer_id', $marketer->id)
            ->whereNotNull('warehouse_inventories.marketer_listing_id')
            ->selectRaw('
                COUNT(DISTINCT ml.id)                                          as total_skus,
                COALESCE(SUM(warehouse_inventories.quantity_on_hand), 0)       as total_on_hand,
                COALESCE(SUM(warehouse_inventories.quantity_reserved), 0)      as total_reserved,
                COALESCE(SUM(warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved), 0) as total_available
            ')
            ->first();

        $lowStockCount = WarehouseInventory::join('marketer_listings as ml', 'ml.id', '=', 'warehouse_inventories.marketer_listing_id')
            ->where('ml.marketer_id', $marketer->id)
            ->whereNotNull('warehouse_inventories.marketer_listing_id')
            ->whereRaw('warehouse_inventories.quantity_on_hand > 0')
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= COALESCE(ml.low_stock_threshold, 5)')
            ->count();

        $outOfStockCount = WarehouseInventory::join('marketer_listings as ml', 'ml.id', '=', 'warehouse_inventories.marketer_listing_id')
            ->where('ml.marketer_id', $marketer->id)
            ->whereNotNull('warehouse_inventories.marketer_listing_id')
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= 0')
            ->count();

        $rows = $this->buildQuery($marketer->id, $request->input('filter'))
            ->paginate(30);

        return view('marketer.inventory.index', compact('stats', 'rows', 'lowStockCount', 'outOfStockCount'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Low Stock
    // ─────────────────────────────────────────────────────────────────────────

    public function lowStock(Request $request): View
    {
        $marketer = $this->marketer();

        $rows = WarehouseInventory::join('marketer_listings as ml', 'ml.id', '=', 'warehouse_inventories.marketer_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'ml.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('ml.marketer_id', $marketer->id)
            ->whereNotNull('warehouse_inventories.marketer_listing_id')
            ->whereRaw('warehouse_inventories.quantity_on_hand > 0')
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= COALESCE(ml.low_stock_threshold, 5)')
            ->select([
                'warehouse_inventories.*',
                'ml.id as listing_id',
                'ml.low_stock_threshold',
                'pv.variant_name',
                'pv.sku',
                'p.name_en',
                'p.name_ar',
                'w.name as warehouse_name',
            ])
            ->orderByRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved ASC')
            ->paginate(30);

        return view('marketer.inventory.low-stock', compact('rows'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Out of Stock
    // ─────────────────────────────────────────────────────────────────────────

    public function outOfStock(Request $request): View
    {
        $marketer = $this->marketer();

        $rows = WarehouseInventory::join('marketer_listings as ml', 'ml.id', '=', 'warehouse_inventories.marketer_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'ml.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('ml.marketer_id', $marketer->id)
            ->whereNotNull('warehouse_inventories.marketer_listing_id')
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= 0')
            ->select([
                'warehouse_inventories.*',
                'ml.id as listing_id',
                'ml.status as listing_status',
                'pv.variant_name',
                'pv.sku',
                'p.name_en',
                'p.name_ar',
                'w.name as warehouse_name',
            ])
            ->orderByDesc('warehouse_inventories.updated_at')
            ->paginate(30);

        return view('marketer.inventory.out-of-stock', compact('rows'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared Query Builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildQuery(string $marketerId, ?string $filter = null): Builder
    {
        $query = WarehouseInventory::join('marketer_listings as ml', 'ml.id', '=', 'warehouse_inventories.marketer_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'ml.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('ml.marketer_id', $marketerId)
            ->whereNotNull('warehouse_inventories.marketer_listing_id')
            ->select([
                'warehouse_inventories.id',
                'warehouse_inventories.marketer_listing_id',
                'warehouse_inventories.quantity_on_hand',
                'warehouse_inventories.quantity_reserved',
                'warehouse_inventories.quantity_inbound',
                'warehouse_inventories.quantity_damaged',
                'ml.id as listing_id',
                'ml.status as listing_status',
                'ml.low_stock_threshold',
                'pv.variant_name',
                'pv.sku',
                'p.name_en',
                'p.name_ar',
                'w.name as warehouse_name',
            ])
            ->addSelect([
                'primary_image' => ProductImage::select('path')
                    ->whereColumn('product_id', 'p.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
                'primary_image_disk' => ProductImage::select('disk')
                    ->whereColumn('product_id', 'p.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
            ]);

        if ($filter === 'low_stock') {
            $query->whereRaw('warehouse_inventories.quantity_on_hand > 0')
                ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= COALESCE(ml.low_stock_threshold, 5)');
        } elseif ($filter === 'out_of_stock') {
            $query->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= 0');
        }

        return $query;
    }
}
