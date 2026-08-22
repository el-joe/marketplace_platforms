<?php

namespace App\Http\Controllers\Partner;

use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\City;
use App\Models\ShippingCarrier;
use App\Models\Vendor;
use App\Models\VendorExceptionalZoneAlert;
use App\Models\Warehouse;
use App\Notifications\Admin\VendorExceptionalZoneAlertCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class ExceptionalZoneAlertController extends Controller
{
    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    /**
     * Main page: vendor sees their alert history + can create new alert
     */
    public function index(): View
    {
        $vendor = $this->vendor();

        $warehouses = Warehouse::where('owner_vendor_id', $vendor->id)
            ->where('type', WarehouseType::SellerOwned)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'country_id']);

        $carriers = ShippingCarrier::where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $myAlerts = VendorExceptionalZoneAlert::where('vendor_id', $vendor->id)
            ->with('warehouse:id,name,code', 'carrier:id,name')
            ->latest()
            ->paginate(15);

        return view('partner.exceptional-zone-alerts.index',
            compact('warehouses', 'carriers', 'myAlerts'));
    }

    /**
     * AJAX: Return cities for a given warehouse's country
     * Called when vendor selects a warehouse — populates the city multi-select
     */
    public function citiesForWarehouse(Request $request): JsonResponse
    {
        $request->validate(['warehouse_id' => 'required|exists:warehouses,id']);

        $vendor = $this->vendor();

        $warehouse = Warehouse::where('id', $request->warehouse_id)
            ->where('owner_vendor_id', $vendor->id)
            ->where('type', WarehouseType::SellerOwned)
            ->firstOrFail();

        // Show all active cities in country, not just zoned ones —
        // vendor may know a city is expensive even before admin zones it.
        $cities = City::where('country_id', $warehouse->country_id)
            ->where('is_active', 1)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'shipping_zone_id']);

        // Load the country's currency code
        $currency = \App\Models\Country::where('id', $warehouse->country_id)
            ->value('currency_code') ?? 'SAR';

        return response()->json([
            'currency' => $currency,
            'cities'   => $cities->map(fn (City $c) => [
                'id'       => $c->id,
                'name_en'  => $c->name_en,
                'name_ar'  => $c->name_ar,
                'has_zone' => !is_null($c->shipping_zone_id),
            ]),
        ]);
    }

    /**
     * Vendor submits the alert
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->vendor();

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'city_ids' => 'required|array|min:1',
            'city_ids.*' => 'required|exists:cities,id',
            'carrier_id' => 'nullable|exists:shipping_carriers,id',
            'reported_carrier_fee' => 'required|integer|min:1',
            'currency' => 'required|in:SAR,AED,OMR,KWD,QAR,BHD,EGP,JOD',
            'vendor_note' => 'nullable|string|max:1000',
        ]);

        $warehouse = Warehouse::where('id', $request->warehouse_id)
            ->where('owner_vendor_id', $vendor->id)
            ->where('type', WarehouseType::SellerOwned)
            ->firstOrFail();

        $invalidCities = City::whereIn('id', $request->city_ids)
            ->where('country_id', '!=', $warehouse->country_id)
            ->count();
        abort_if($invalidCities > 0, 422,
            __('partner.exceptional_zone_alerts.messages.cities_country_mismatch'));

        $existingPending = VendorExceptionalZoneAlert::where('vendor_id', $vendor->id)
            ->where('warehouse_id', $request->warehouse_id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            $overlap = array_intersect(
                $request->city_ids,
                $existingPending->city_ids ?? []
            );
            if (!empty($overlap)) {
                return back()->withErrors([
                    'city_ids' => __('partner.exceptional_zone_alerts.messages.overlap_pending_alert'),
                ]);
            }
        }

        DB::transaction(function () use ($vendor, $request, $warehouse) {
            VendorExceptionalZoneAlert::create([
                'vendor_id' => $vendor->id,
                'warehouse_id' => $request->warehouse_id,
                'city_ids' => $request->city_ids,
                'carrier_id' => $request->carrier_id,
                'reported_carrier_fee' => $request->reported_carrier_fee,
                'currency' => $request->currency,
                'vendor_note' => $request->vendor_note,
                'status' => 'pending',
            ]);

            $admins = Admin::permission('settings.view')->get();
            Notification::send($admins, new VendorExceptionalZoneAlertCreated(
                $vendor, $warehouse, count($request->city_ids)
            ));
        });

        return redirect()->route('partner.exceptional-zone-alerts.index')
            ->with('success', __('partner.exceptional_zone_alerts.messages.submitted'));
    }

    /**
     * Vendor cancels a pending alert
     */
    public function cancel(VendorExceptionalZoneAlert $alert): RedirectResponse
    {
        $vendor = $this->vendor();
        abort_unless($alert->vendor_id === $vendor->id, 403);
        abort_unless($alert->isPending(), 422, __('partner.exceptional_zone_alerts.messages.only_pending_can_be_cancelled'));

        $alert->update(['status' => 'rejected', 'admin_note' => 'Cancelled by vendor.']);

        return back()->with('success', __('partner.exceptional_zone_alerts.messages.cancelled'));
    }
}
