<?php

namespace App\Http\Controllers\Partner;

use App\Enums\GlobalSystemType;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorCityShippingSurcharge;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Traits\HasDataTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CityShippingSurchargeController extends Controller
{
    use HasDataTable;

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index(Request $request): View|JsonResponse
    {
        if (!$request->has('draw')) {
            $vendorId = $this->vendorId();

            $hasFbpListings = VendorListing::where('vendor_id', $vendorId)
                ->where('global_system_type', GlobalSystemType::MerchantFbp->value)
                ->whereNotIn('status', ['archived'])
                ->exists();

            $warehouses = Warehouse::where('owner_vendor_id', $vendorId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

            return view('partner.city-surcharges', compact('hasFbpListings', 'warehouses'));
        }

        $query = VendorCityShippingSurcharge::query()
            ->with('warehouse')
            ->where('vendor_id', $this->vendorId())
            ->select('vendor_city_shipping_surcharges.*');

        $columns = [
            ['orderable_column' => 'warehouse_id'],
            ['orderable_column' => 'extra_amount_cents'],
            [],
            [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (VendorCityShippingSurcharge $surcharge) {
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
        $vendorId = $this->vendorId();

        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('owner_vendor_id', $vendorId)],
            'extra_amount_cents' => ['required', 'integer', 'min:0'],
        ]);

        $surcharge = VendorCityShippingSurcharge::updateOrCreate(
            ['vendor_id' => $vendorId, 'warehouse_id' => $validated['warehouse_id']],
            ['extra_amount_cents' => $validated['extra_amount_cents'], 'is_active' => true],
        );

        return response()->json(['message' => __('partner.city_surcharges.messages.saved'), 'data' => ['id' => $surcharge->id]]);
    }

    public function update(Request $request, VendorCityShippingSurcharge $surcharge): JsonResponse
    {
        abort_unless($surcharge->vendor_id === $this->vendorId(), 404);
        $vendorId = $this->vendorId();

        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('owner_vendor_id', $vendorId)],
            'extra_amount_cents' => ['required', 'integer', 'min:0'],
        ]);

        $surcharge->update($validated);

        return response()->json(['message' => __('partner.city_surcharges.messages.updated')]);
    }

    public function toggleActive(VendorCityShippingSurcharge $surcharge): JsonResponse
    {
        abort_unless($surcharge->vendor_id === $this->vendorId(), 404);

        $surcharge->update(['is_active' => !$surcharge->is_active]);

        return response()->json(['message' => $surcharge->is_active ? 'Surcharge activated.' : 'Surcharge deactivated.']);
    }
}
