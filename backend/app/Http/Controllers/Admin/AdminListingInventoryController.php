<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminListing;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminListingInventoryController extends Controller
{
    public function index(string $listingId): JsonResponse
    {
        $listing = AdminListing::with('warehouseInventories.warehouse')->findOrFail($listingId);

        return response()->json([
            'success' => true,
            'data' => $listing->warehouseInventories,
        ]);
    }

    public function store(Request $request, string $listingId): JsonResponse
    {
        $listing = AdminListing::findOrFail($listingId);

        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'qty_on_hand' => ['required', 'integer', 'min:0'],
            'qty_inbound' => ['nullable', 'integer', 'min:0'],
            'qty_damaged' => ['nullable', 'integer', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:50'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
        ]);

        $exists = WarehouseInventory::where('admin_listing_id', $listing->id)
            ->where('warehouse_id', $data['warehouse_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => __('admin.listing_inventory.already_exists'),
            ], 422);
        }

        $inventory = DB::transaction(function () use ($listing, $data) {
            return WarehouseInventory::create([
                'admin_listing_id' => $listing->id,
                'vendor_listing_id' => null,
                'warehouse_id' => $data['warehouse_id'],
                'quantity_on_hand' => $data['qty_on_hand'],
                'quantity_inbound' => $data['qty_inbound'] ?? 0,
                'quantity_damaged' => $data['qty_damaged'] ?? 0,
                'bin_location' => $data['bin_location'] ?? null,
                'reorder_point' => $data['reorder_point'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => __('admin.listing_inventory.created'),
            'data' => $inventory->fresh('warehouse'),
        ]);
    }

    public function update(Request $request, string $listingId, string $inventoryId): JsonResponse
    {
        $inventory = WarehouseInventory::where('admin_listing_id', $listingId)
            ->where('id', $inventoryId)
            ->firstOrFail();

        $data = $request->validate([
            'qty_on_hand' => ['sometimes', 'required', 'integer', 'min:0'],
            'qty_inbound' => ['nullable', 'integer', 'min:0'],
            'qty_damaged' => ['nullable', 'integer', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:50'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($inventory, $data) {
            if (array_key_exists('qty_on_hand', $data)) {
                $inventory->quantity_on_hand = $data['qty_on_hand'];
            }
            if (array_key_exists('qty_inbound', $data)) {
                $inventory->quantity_inbound = $data['qty_inbound'];
            }
            if (array_key_exists('qty_damaged', $data)) {
                $inventory->quantity_damaged = $data['qty_damaged'];
            }
            if (array_key_exists('bin_location', $data)) {
                $inventory->bin_location = $data['bin_location'];
            }
            if (array_key_exists('reorder_point', $data)) {
                $inventory->reorder_point = $data['reorder_point'];
            }
            $inventory->save();
        });

        return response()->json([
            'success' => true,
            'message' => __('admin.listing_inventory.updated'),
            'data' => $inventory->fresh('warehouse'),
        ]);
    }
}
