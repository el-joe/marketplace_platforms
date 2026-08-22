<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Enums\DeliveryAgentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryZone;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function index(Request $request): View|StreamedResponse
    {
        $supervisor = auth('shipping_supervisor')->user();

        if ($request->filled('export')) {
            return $this->exportAgents($request, $supervisor);
        }

        $agents = $this->buildAgentsQuery($request, $supervisor)
            ->with('zone:id,name')
            ->withCount('assignments')
            ->latest()
            ->paginate(20);

        return view('carrier.agents.index', compact('agents'));
    }

    /** Shared query for the index view and the export, scoped to the supervisor's company. */
    private function buildAgentsQuery(Request $request, $supervisor): Builder
    {
        // Compat shim: supervisors created before country scoping fall back to
        // their company's home country until backfilled with their own country_id.
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        $query = DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        return $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('status', $v),
        ]);
    }

    private function exportAgents(Request $request, $supervisor): StreamedResponse
    {
        $agents = $this->buildAgentsQuery($request, $supervisor)
            ->withCount('assignments')
            ->latest()
            ->get();

        $headers = ['Name', 'Email', 'Status', 'Assignments', 'Joined'];

        $rows = $agents->map(fn (DeliveryAgent $agent) => [
            $agent->name,
            $agent->email,
            $agent->status?->value,
            (int) $agent->assignments_count,
            $agent->created_at?->format('Y-m-d') ?? '—',
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('carrier-agents', $headers, $rows),
            'csv' => $this->exportCsv('carrier-agents', $headers, $rows),
            'word' => $this->exportWord('carrier-agents', 'Carrier Agents', $rows),
            default => abort(400, __('carrier.errors.invalid_export_format')),
        };
    }

    public function create(): View
    {
        $this->requirePermission('manage_agents');

        $supervisor = auth('shipping_supervisor')->user();
        $countryId  = $supervisor->country_id ?? $supervisor->company?->country_id;

        $zones = DeliveryZone::where('is_active', true)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->withCount(['agents' => fn ($q) => $q->whereIn('status', ['active', 'on_shift'])])
            ->orderBy('name')
            ->get(['id', 'name', 'max_active_agents']);

        return view('carrier.agents.create', compact('zones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('manage_agents');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'unique:delivery_agents,email'],
            'phone'        => ['required', 'string', 'max:30', 'unique:delivery_agents,phone'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'vehicle_type' => ['required', 'in:motorcycle,car,van,bicycle'],
            'national_id'  => ['nullable', 'string', 'max:50'],
            'zone_id'      => ['nullable', 'exists:delivery_zones,id'],
        ]);

        $supervisor = auth('shipping_supervisor')->user();
        $supervisorCountry = $supervisor->country_id ?? $supervisor->company?->country_id;

        if (!empty($data['zone_id'])) {
            $zone = DeliveryZone::find($data['zone_id']);

            if ($supervisorCountry && $zone->country_id !== $supervisorCountry) {
                return back()->withErrors(['zone_id' => __('carrier.agents.zone_country_mismatch')])->withInput();
            }

            if ($zone->isAtCapacity()) {
                return back()->withErrors(['zone_id' => __('carrier.agents.zone_at_capacity', ['name' => $zone->name])])->withInput();
            }
        }

        DeliveryAgent::create([
            ...$data,
            'country_id'             => $supervisorCountry,
            'agent_type'             => 'third_party',
            'shipping_company_id'    => $supervisor->shipping_company_id,
            'added_by_supervisor_id' => $supervisor->id,
            'status'                 => DeliveryAgentStatus::Active,
        ]);

        return redirect()->route('carrier.agents.index')
            ->with('success', __('carrier.agents.added_success'));
    }

    public function show(string $id): View
    {
        $this->requirePermission('manage_agents');

        $agent = $this->agentForCurrentCompany($id);
        $agent->load('zone', 'documents');

        $supervisor = auth('shipping_supervisor')->user();
        $countryId  = $supervisor->country_id ?? $supervisor->company?->country_id;

        $zones = DeliveryZone::where('is_active', true)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->withCount(['agents' => fn ($q) => $q->whereIn('status', ['active', 'on_shift'])])
            ->orderBy('name')
            ->get(['id', 'name', 'max_active_agents']);

        return view('carrier.agents.show', compact('agent', 'zones'));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        $agent = $this->agentForCurrentCompany($id);

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'phone'                   => ['required', 'string', 'max:30',
                                          'unique:delivery_agents,phone,' . $agent->id],
            'vehicle_type'            => ['required', 'in:motorcycle,car,van,bicycle'],
            'national_id'             => ['nullable', 'string', 'max:50'],
            'vehicle_plate'           => ['nullable', 'string', 'max:20'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'password'                => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'name'                    => $data['name'],
            'phone'                   => $data['phone'],
            'vehicle_type'            => $data['vehicle_type'],
            'national_id'             => $data['national_id'] ?? null,
            'vehicle_plate'           => $data['vehicle_plate'] ?? null,
            'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $agent->update($updateData);

        return response()->json([
            'success' => true,
            'message' => __('carrier.agents.updated_success'),
        ]);
    }

    public function assignZone(Request $request, string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        $agent = $this->agentForCurrentCompany($id);

        $request->validate([
            'zone_id' => ['nullable', 'exists:delivery_zones,id'],
        ]);

        $zoneId = $request->input('zone_id');

        if ($zoneId) {
            $zone = DeliveryZone::find($zoneId);

            $supervisor = auth('shipping_supervisor')->user();
            $supervisorCountry = $supervisor->country_id ?? $supervisor->company?->country_id;

            if ($supervisorCountry && $zone->country_id !== $supervisorCountry) {
                return response()->json(['success' => false, 'message' => __('carrier.agents.zone_country_mismatch')], 422);
            }

            if ($zoneId !== $agent->zone_id && $zone->isAtCapacity()) {
                return response()->json([
                    'success' => false,
                    'message' => __('carrier.agents.zone_at_capacity', ['name' => $zone->name]),
                ], 422);
            }
        }

        $agent->update(['zone_id' => $zoneId]);

        $zoneName = $zoneId ? DeliveryZone::find($zoneId)?->name : __('carrier.agents.no_zone');

        return response()->json(['success' => true, 'message' => __('carrier.agents.zone_updated', ['name' => $zoneName])]);
    }

    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        $agent = $this->agentForCurrentCompany($id);

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $agent->update(['password' => $request->input('password')]);

        return response()->json([
            'success' => true,
            'message' => __('carrier.agents.password_reset_success'),
        ]);
    }

    public function suspend(string $id): RedirectResponse
    {
        $this->requirePermission('manage_agents');

        $agent = $this->agentForCurrentCompany($id);
        $agent->update(['status' => DeliveryAgentStatus::Suspended]);

        return back()->with('success', __('carrier.agents.suspended_success'));
    }

    public function activate(string $id): RedirectResponse
    {
        $this->requirePermission('manage_agents');

        $agent = $this->agentForCurrentCompany($id);
        $agent->update(['status' => DeliveryAgentStatus::Active]);

        return back()->with('success', __('carrier.agents.activated_success'));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function requirePermission(string $perm): void
    {
        $supervisor = auth('shipping_supervisor')->user();
        abort_unless($supervisor->hasPermission($perm), 403, __('carrier.errors.no_permission_generic'));
    }

    private function agentForCurrentCompany(string $id): DeliveryAgent
    {
        $supervisor = auth('shipping_supervisor')->user();
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        return DeliveryAgent::where('id', $id)
            ->where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->firstOrFail();
    }
}
