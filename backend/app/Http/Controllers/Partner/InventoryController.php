<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ProductImage;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InventoryController extends Controller
{
    use HasDataTable;
    use HasExport;

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function authoriseListing(VendorListing $listing): void
    {
        if ($listing->vendor_id !== $this->vendorId()) {
            abort(403);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index — all inventory rows for vendor
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportInventory($request);
        }

        $vendorId = $this->vendorId();

        // Summary stats
        $stats = WarehouseInventory::join('vendor_listings as vl', 'vl.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->where('vl.vendor_id', $vendorId)
            ->selectRaw('
                COUNT(DISTINCT vl.id)                             as total_skus,
                COALESCE(SUM(quantity_on_hand), 0)                as total_on_hand,
                COALESCE(SUM(quantity_reserved), 0)               as total_reserved,
                COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as total_available
            ')
            ->first();

        $lowStockCount = WarehouseInventory::join('vendor_listings as vl', 'vl.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->where('vl.vendor_id', $vendorId)
            ->whereRaw('warehouse_inventories.quantity_on_hand > 0')
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= vl.low_stock_threshold')
            ->count();

        $outOfStockCount = WarehouseInventory::join('vendor_listings as vl', 'vl.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->where('vl.vendor_id', $vendorId)
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= 0')
            ->count();

        return view('partner.inventory.index', compact('stats', 'lowStockCount', 'outOfStockCount'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildInventoryQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $vendorId = $this->vendorId();

        $query = WarehouseInventory::join('vendor_listings as vl', 'vl.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('vl.vendor_id', $vendorId)
            ->select([
                'warehouse_inventories.id',
                'warehouse_inventories.vendor_listing_id',
                'warehouse_inventories.quantity_on_hand',
                'warehouse_inventories.quantity_reserved',
                'warehouse_inventories.quantity_inbound',
                'warehouse_inventories.quantity_damaged',
                'warehouse_inventories.bin_location',
                'warehouse_inventories.reorder_point',
                'vl.id as listing_id',
                'vl.status as listing_status',
                'vl.low_stock_threshold',
                'pv.variant_name',
                'pv.sku',
                'p.name_en',
                'p.name_ar',
                'w.name as warehouse_name',
                'w.code as warehouse_code',
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

        $query = $this->applyFilters($query, $request, [
            'filter' => function ($q, $v) {
                if ($v === 'low_stock') {
                    return $q->whereRaw('warehouse_inventories.quantity_on_hand > 0')
                        ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= vl.low_stock_threshold');
                }
                if ($v === 'out_of_stock') {
                    return $q->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= 0');
                }
                return $q;
            },
        ]);

        return $query;
    }

    private function exportInventory(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $items = $this->buildInventoryQuery($request)->get();

        $headers = ['Product', 'Variant', 'SKU', 'Warehouse', 'On Hand', 'Reserved', 'Available', 'Inbound', 'Damaged'];

        $rows = $items->map(fn($row) => [
            $row->name_en,
            $row->variant_name ?: '—',
            $row->sku,
            $row->warehouse_name,
            $row->quantity_on_hand,
            $row->quantity_reserved,
            $row->quantity_on_hand - $row->quantity_reserved,
            $row->quantity_inbound ?? 0,
            $row->quantity_damaged ?? 0,
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('inventory', $headers, $rows),
            'csv' => $this->exportCsv('inventory', $headers, $rows),
            'word' => $this->exportWord('inventory', 'Inventory', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inventory DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['p.name_en', 'p.name_ar', 'pv.sku']],
            ['orderable_column' => 'pv.variant_name'],
            ['orderable_column' => 'w.name'],
            ['orderable_column' => 'warehouse_inventories.quantity_on_hand'],
            ['orderable_column' => 'warehouse_inventories.quantity_reserved'],
            [],
            ['orderable_column' => 'warehouse_inventories.quantity_inbound'],
            ['orderable_column' => 'warehouse_inventories.quantity_damaged'],
            [],
        ];

        $query = $this->buildInventoryQuery($request);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $imageUrl = null;
            if ($row->primary_image) {
                $imageUrl = Storage::disk($row->primary_image_disk ?? 'public')->url($row->primary_image);
            }

            $available = $row->quantity_on_hand - $row->quantity_reserved;
            $threshold = (int) ($row->low_stock_threshold ?? 5);
            $stockCls = $available <= 0 ? 'text-red-600 font-bold'
                : ($available <= $threshold ? 'text-orange-500 font-semibold' : 'text-gray-800');

            return [
                'id' => $row->id,
                'listing_id' => $row->listing_id,
                'name_en' => $row->name_en,
                'name_ar' => $row->name_ar,
                'image_url' => $imageUrl,
                'variant_name' => $row->variant_name ?: '—',
                'sku' => $row->sku,
                'warehouse_name' => $row->warehouse_name,
                'warehouse_code' => $row->warehouse_code,
                'quantity_on_hand' => $row->quantity_on_hand,
                'quantity_reserved' => $row->quantity_reserved,
                'available' => "<span class=\"{$stockCls} text-xs\">{$available}</span>",
                'available_raw' => $available,
                'quantity_inbound' => $row->quantity_inbound ?? 0,
                'quantity_damaged' => $row->quantity_damaged ?? 0,
                'listing_url' => route('partner.listings.show', $row->listing_id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Low Stock
    // ─────────────────────────────────────────────────────────────────────────

    public function lowStock(): View
    {
        $vendorId = $this->vendorId();

        $rows = WarehouseInventory::join('vendor_listings as vl', 'vl.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('vl.vendor_id', $vendorId)
            ->whereRaw('warehouse_inventories.quantity_on_hand > 0')
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= vl.low_stock_threshold')
            ->select([
                'warehouse_inventories.*',
                'vl.id as listing_id',
                'vl.low_stock_threshold',
                'pv.variant_name',
                'pv.sku',
                'p.name_en',
                'p.name_ar',
                'w.name as warehouse_name',
            ])
            ->orderByRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved ASC')
            ->paginate(25);

        return view('partner.inventory.low-stock', compact('rows'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Out of Stock
    // ─────────────────────────────────────────────────────────────────────────

    public function outOfStock(): View
    {
        $vendorId = $this->vendorId();

        $rows = WarehouseInventory::join('vendor_listings as vl', 'vl.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'warehouse_inventories.warehouse_id')
            ->where('vl.vendor_id', $vendorId)
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved <= 0')
            ->select([
                'warehouse_inventories.*',
                'vl.id as listing_id',
                'vl.status as listing_status',
                'pv.variant_name',
                'pv.sku',
                'p.name_en',
                'p.name_ar',
                'w.name as warehouse_name',
            ])
            ->orderByDesc('warehouse_inventories.updated_at')
            ->paginate(25);

        return view('partner.inventory.out-of-stock', compact('rows'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Movements — paginated history for a listing
    // ─────────────────────────────────────────────────────────────────────────

    public function movements(VendorListing $listing): View
    {
        $this->authoriseListing($listing);

        $listing->load(['productVariant.product', 'warehouseInventories.warehouse']);

        $inventoryIds = $listing->warehouseInventories->pluck('id');

        $movements = InventoryMovement::whereIn('warehouse_inventory_id', $inventoryIds)
            ->with('warehouseInventory.warehouse')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('partner.inventory.movements', compact('listing', 'movements'));
    }
}
