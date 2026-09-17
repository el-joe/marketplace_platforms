<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\WarehouseInventory;
use App\Services\Customer\BuyBoxRebuildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-24: admin-panel triggers for two operational tools that,
 * until now, only existed as artisan commands (P-13's `inventory:reconcile`
 * and P-19's `buybox:rebuild`) with no in-panel entry point.
 *
 * Kept intentionally thin: both actions reuse the exact same read/write
 * logic as their console commands (InventoryReconcile, RebuildBuyBox) so
 * there is a single source of truth, per enhancement.md rule 0.1.3.
 */
class SystemToolsController extends Controller
{
    /**
     * Report (and optionally fix) drift between
     * warehouse_inventories.quantity_reserved and the sum of open
     * order_item_allocations — the same query as `inventory:reconcile`.
     */
    public function inventoryReconcile(Request $request): JsonResponse
    {
        $fix = $request->boolean('fix');

        $expected = DB::table('order_item_allocations')
            ->where('status', 'reserved')
            ->groupBy('warehouse_inventory_id')
            ->select('warehouse_inventory_id', DB::raw('SUM(quantity) as expected_reserved'))
            ->pluck('expected_reserved', 'warehouse_inventory_id');

        $drifted = [];

        foreach (WarehouseInventory::all(['id', 'quantity_reserved']) as $row) {
            $expectedReserved = (int) ($expected[$row->id] ?? 0);

            if ($expectedReserved !== (int) $row->quantity_reserved) {
                $drifted[] = [
                    'warehouse_inventory_id' => $row->id,
                    'quantity_reserved'      => (int) $row->quantity_reserved,
                    'expected_reserved'      => $expectedReserved,
                ];

                if ($fix) {
                    DB::table('warehouse_inventories')
                        ->where('id', $row->id)
                        ->update(['quantity_reserved' => $expectedReserved]);
                }
            }
        }

        return response()->json([
            'drift_count' => count($drifted),
            'fixed'       => $fix,
            'drift'       => $drifted,
        ]);
    }

    /**
     * Rebuild the product_country_buybox read model for one country (or
     * every launched country) — same logic as `buybox:rebuild`.
     */
    public function buyboxRebuild(Request $request, BuyBoxRebuildService $rebuilder): JsonResponse
    {
        $countryId = $request->input('country_id');

        $countries = $countryId
            ? Country::query()->where('id', $countryId)->get()
            : Country::query()->get();

        if ($countries->isEmpty()) {
            return response()->json(['message' => 'No matching country found.'], 422);
        }

        $results = [];

        foreach ($countries as $country) {
            $results[] = [
                'country_id'   => $country->id,
                'country_name' => $country->name_en,
                'rows'         => $rebuilder->rebuildCountry($country),
            ];
        }

        return response()->json(['results' => $results]);
    }
}
