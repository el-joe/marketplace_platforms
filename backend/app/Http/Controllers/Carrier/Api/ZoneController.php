<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryZone;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $countryId  = $supervisor->country_id ?? $supervisor->company?->country_id;

        $zones = DeliveryZone::where('is_active', true)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->withCount(['agents' => fn ($q) => $q->whereIn('status', ['active', 'on_shift'])])
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'zones' => $zones->map(fn (DeliveryZone $zone) => [
                'id'                => $zone->id,
                'name'              => $zone->name,
                'code'              => $zone->code,
                'max_active_agents' => $zone->max_active_agents,
                'active_agents'     => (int) $zone->agents_count,
                'at_capacity'       => $zone->isAtCapacity(),
            ]),
        ]);
    }
}
