<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    // ── GET /zones ────────────────────────────────────────────────────────────
    // Returns zones scoped to the supervisor's country, for populating the
    // zone dropdown when creating/editing agents in the carrier panel.

    public function index(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor')->user();
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $zones = DeliveryZone::where('is_active', true)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_active_agents']);

        return response()->json(['zones' => $zones]);
    }
}
