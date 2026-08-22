<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryAgentStatus;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\DeliveryAgent;
use App\Models\DeliveryZone;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryZoneController extends Controller
{
    use HasDataTable;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $zones = DeliveryZone::with('country')
            ->withCount(['agents' => fn($q) => $q->whereIn('status', [DeliveryAgentStatus::Active, DeliveryAgentStatus::OnShift])])
            ->orderBy('name')
            ->get();

        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en']);
        $cities    = City::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'country_id', 'name_en', 'name_ar']);

        return view('admin.delivery.zones.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Delivery'],
                ['label' => 'Zones'],
            ],
            'zones' => $zones,
            'countries' => $countries,
            'cities' => $cities,
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'unique:delivery_zones,code', 'regex:/^[A-Z0-9_-]+$/'],
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['exists:cities,id'],
            'base_delivery_fee' => ['required', 'integer', 'min:0'],
            'cod_fee' => ['required', 'integer', 'min:0'],
            'max_active_agents' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $this->assertCitiesBelongToCountry($validated['city_ids'] ?? [], $validated['country_id']);

        $zone = DeliveryZone::create([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'city_ids' => $validated['city_ids'] ?? null,
            'base_delivery_fee' => $validated['base_delivery_fee'],
            'cod_fee' => $validated['cod_fee'],
            'max_active_agents' => $validated['max_active_agents'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Zone created.',
            'zone' => $zone->load('country'),
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, DeliveryZone $zone): View|JsonResponse
    {
        $zone->load('country');

        // AJAX: return JSON (used by edit modal population)
        if ($request->expectsJson()) {
            $cityDetails = [];
            if (!empty($zone->city_ids)) {
                $cityDetails = City::whereIn('id', $zone->city_ids)
                    ->get(['id', 'name_en', 'name_ar'])
                    ->toArray();
            }
            return response()->json(['zone' => $zone, 'city_details' => $cityDetails]);
        }

        // HTML: return full page
        $agents = DeliveryAgent::where('zone_id', $zone->id)
            ->with('country')
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        $cities = !empty($zone->city_ids)
            ? City::whereIn('id', $zone->city_ids)->orderBy('name_en')->get(['id', 'name_en', 'name_ar'])
            : collect();

        // Agents from the same country NOT in this zone (for add-to-zone UI)
        $availableAgents = DeliveryAgent::where('country_id', $zone->country_id)
            ->where(fn($q) => $q->whereNull('zone_id')->orWhere('zone_id', '!=', $zone->id))
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'zone_id']);

        return view('admin.delivery.zones.show', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Delivery'],
                ['label' => 'Zones', 'url' => route('admin.delivery.zones.index')],
                ['label' => $zone->name],
            ],
            'zone'            => $zone,
            'agents'          => $agents,
            'cities'          => $cities,
            'availableAgents' => $availableAgents,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, DeliveryZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'unique:delivery_zones,code,' . $zone->id, 'regex:/^[A-Z0-9_-]+$/'],
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['exists:cities,id'],
            'base_delivery_fee' => ['required', 'integer', 'min:0'],
            'cod_fee' => ['required', 'integer', 'min:0'],
            'max_active_agents' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $this->assertCitiesBelongToCountry($validated['city_ids'] ?? [], $validated['country_id']);

        $zone->update([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'city_ids' => $validated['city_ids'] ?? null,
            'base_delivery_fee' => $validated['base_delivery_fee'],
            'cod_fee' => $validated['cod_fee'],
            'max_active_agents' => $validated['max_active_agents'] ?? null,
            'is_active' => $validated['is_active'] ?? $zone->is_active,
        ]);

        return response()->json(['success' => true, 'message' => 'Zone updated.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertCitiesBelongToCountry(array $cityIds, string $countryId): void
    {
        if (empty($cityIds)) {
            return;
        }

        $validCount = City::whereIn('id', $cityIds)->where('country_id', $countryId)->count();

        abort_if($validCount !== count($cityIds), 422, 'All selected cities must belong to the zone\'s country.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(DeliveryZone $zone): JsonResponse
    {
        if ($zone->agents()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete zone with assigned agents. Reassign them first.'], 422);
        }

        $zone->delete();

        return response()->json(['success' => true, 'message' => 'Zone deleted.']);
    }

    // ── Assign Agents (bulk) ──────────────────────────────────────────────────

    public function assignAgents(Request $request, DeliveryZone $zone): JsonResponse
    {
        $request->validate([
            'agent_ids' => ['required', 'array'],
            'agent_ids.*' => ['exists:delivery_agents,id'],
        ]);

        DeliveryAgent::whereIn('id', $request->input('agent_ids'))
            ->update(['zone_id' => $zone->id]);

        $count = count($request->input('agent_ids'));

        return response()->json(['success' => true, 'message' => "{$count} agent(s) assigned to {$zone->name}."]);
    }

    // ── Live Map Data ─────────────────────────────────────────────────────────

    public function getAgentMap(DeliveryZone $zone): JsonResponse
    {
        $agents = DeliveryAgent::where('zone_id', $zone->id)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->select(['id', 'name', 'status', 'is_available', 'current_latitude', 'current_longitude', 'last_location_at'])
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'status' => $a->status?->value,
                'is_available' => (bool) $a->is_available,
                'lat' => (float) $a->current_latitude,
                'lng' => (float) $a->current_longitude,
                'updated' => $a->last_location_at?->diffForHumans() ?? 'Unknown',
            ]);

        return response()->json(['agents' => $agents]);
    }
}
