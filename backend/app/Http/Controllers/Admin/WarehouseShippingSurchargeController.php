<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\WarehouseShippingSurcharge;
use App\Traits\HasDataTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseShippingSurchargeController extends Controller
{
    use HasDataTable;

    public function index(Request $request): View|JsonResponse
    {
        if (!$request->has('draw')) {
            $warehouses = Warehouse::where('is_active', true)
                ->whereNull('owner_vendor_id')
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

            return view('admin.warehouses.shipping-surcharges', compact('warehouses'));
        }

        $query = WarehouseShippingSurcharge::query()
            ->with('warehouse')
            ->select('warehouse_shipping_surcharges.*');

        $columns = [
            ['orderable_column' => 'warehouse_id'],
            ['orderable_column' => 'extra_amount_cents'],
            [],
            [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (WarehouseShippingSurcharge $surcharge) {
            return [
                'id' => $surcharge->id,
                'warehouse' => $surcharge->warehouse?->name,
                'warehouse_code' => $surcharge->warehouse?->code,
                'extra_amount' => number_format($surcharge->extra_amount_cents / 100, 2),
                'is_active' => $surcharge->is_active,
                'actions' => [
                    'id' => $surcharge->id,
                    'warehouse_id' => $surcharge->warehouse_id,
                    'extra_amount_cents' => $surcharge->extra_amount_cents,
                ],
            ];
        });
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'extra_amount_cents' => ['required', 'integer', 'min:0'],
        ]);

        $surcharge = WarehouseShippingSurcharge::updateOrCreate(
            ['warehouse_id' => $validated['warehouse_id']],
            ['extra_amount_cents' => $validated['extra_amount_cents'], 'is_active' => true],
        );

        return response()->json(['message' => 'Warehouse surcharge saved.', 'data' => ['id' => $surcharge->id]]);
    }

    public function update(Request $request, WarehouseShippingSurcharge $surcharge): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'extra_amount_cents' => ['required', 'integer', 'min:0'],
        ]);

        $surcharge->update($validated);

        return response()->json(['message' => 'Warehouse surcharge updated.']);
    }

    public function toggleActive(WarehouseShippingSurcharge $surcharge): JsonResponse
    {
        $surcharge->update(['is_active' => !$surcharge->is_active]);

        return response()->json(['message' => $surcharge->is_active ? 'Surcharge activated.' : 'Surcharge deactivated.']);
    }
}
