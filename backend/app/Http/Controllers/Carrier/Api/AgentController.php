<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Enums\DeliveryAgentStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryZone;
use App\Models\ShippingCompanySupervisor;
use App\Services\Carrier\AgentRosterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    public function __construct(private readonly AgentRosterService $agentRosterService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $query = $this->scopedQuery($supervisor);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $agents = $query->with('zone:id,name')
            ->withCount('assignments')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($agents->items())->map(fn (DeliveryAgent $a) => $this->transform($a)),
                'meta' => [
                    'current_page' => $agents->currentPage(),
                    'last_page'    => $agents->lastPage(),
                    'per_page'     => $agents->perPage(),
                    'total'        => $agents->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('manage_agents');

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $company    = $supervisor->company;
        $countryId  = $supervisor->country_id ?? $company?->country_id;

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:delivery_agents,email'],
            'phone'         => ['required', 'string', 'max:30', 'unique:delivery_agents,phone'],
            'password'      => ['required', 'string', 'min:8'],
            'vehicle_type'  => ['required', 'in:motorcycle,car,van,bicycle'],
            'license_plate' => ['nullable', 'string', 'max:20'],
            'zone_id'       => ['nullable', 'exists:delivery_zones,id'],
        ]);

        $data['country_id'] = $countryId;

        try {
            $agent = $this->agentRosterService->createAgent($company, $supervisor, $data);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }

        return ApiResponse::success(['agent' => $this->transform($agent)], 'Agent created.', 201);
    }

    public function show(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        $agent = $this->findAgent($supervisor, $id);
        $agent->load('zone', 'documents');

        return ApiResponse::success(['agent' => $this->transform($agent, detailed: true)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $agent = $this->findAgent($supervisor, $id);

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'phone'                   => ['required', 'string', 'max:30', 'unique:delivery_agents,phone,' . $agent->id],
            'vehicle_type'            => ['required', 'in:motorcycle,car,van,bicycle'],
            'national_id'             => ['nullable', 'string', 'max:50'],
            'vehicle_plate'           => ['nullable', 'string', 'max:20'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'password'                => ['nullable', 'string', 'min:8'],
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

        return ApiResponse::success(['agent' => $this->transform($agent->fresh())], 'Agent updated.');
    }

    public function assignZone(Request $request, string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $agent = $this->findAgent($supervisor, $id);

        $request->validate([
            'zone_id' => ['nullable', 'exists:delivery_zones,id'],
        ]);

        $zoneId = $request->input('zone_id');
        $supervisorCountry = $supervisor->country_id ?? $supervisor->company?->country_id;

        if ($zoneId) {
            $zone = DeliveryZone::find($zoneId);

            if ($supervisorCountry && $zone->country_id !== $supervisorCountry) {
                return ApiResponse::error('The selected zone is not in your country.', [], 422);
            }

            if ($zoneId !== $agent->zone_id && $zone->isAtCapacity()) {
                return ApiResponse::error("Zone \"{$zone->name}\" is at full capacity.", [], 422);
            }
        }

        $agent->update(['zone_id' => $zoneId]);

        return ApiResponse::success(['agent' => $this->transform($agent->fresh())], 'Zone updated.');
    }

    public function suspend(string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $agent = $this->findAgent($supervisor, $id);

        $agent->update(['status' => DeliveryAgentStatus::Suspended]);

        return ApiResponse::success(['agent' => $this->transform($agent->fresh())], 'Agent suspended.');
    }

    public function activate(string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $agent = $this->findAgent($supervisor, $id);

        $agent->update(['status' => DeliveryAgentStatus::Active]);

        return ApiResponse::success(['agent' => $this->transform($agent->fresh())], 'Agent activated.');
    }

    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $this->requirePermission('manage_agents');

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $agent = $this->findAgent($supervisor, $id);

        $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $agent->update(['password' => $request->input('password')]);

        return ApiResponse::success(null, 'Password reset.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function scopedQuery(ShippingCompanySupervisor $supervisor): Builder
    {
        $countryId = $supervisor->country_id ?? $supervisor->company?->country_id;

        return DeliveryAgent::where('shipping_company_id', $supervisor->shipping_company_id)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId));
    }

    private function findAgent(ShippingCompanySupervisor $supervisor, string $id): DeliveryAgent
    {
        return $this->scopedQuery($supervisor)->where('id', $id)->firstOrFail();
    }

    private function requirePermission(string $perm): void
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        abort_unless($supervisor->hasPermission($perm), 403, "You do not have the required permission: {$perm}.");
    }

    private function transform(DeliveryAgent $agent, bool $detailed = false): array
    {
        $base = [
            'id'             => $agent->id,
            'name'           => $agent->name,
            'email'          => $agent->email,
            'phone'          => $agent->phone,
            'status'         => $agent->status?->value,
            'vehicle_type'   => $agent->vehicle_type?->value,
            'vehicle_plate'  => $agent->vehicle_plate,
            'is_available'   => $agent->is_available,
            'rating_avg'     => $agent->rating_avg,
            'zone'           => $agent->zone ? ['id' => $agent->zone->id, 'name' => $agent->zone->name] : null,
            'assignments_count' => $agent->assignments_count ?? null,
            'created_at'     => $agent->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base['national_id']             = $agent->national_id;
            $base['emergency_contact_name']  = $agent->emergency_contact_name;
            $base['emergency_contact_phone'] = $agent->emergency_contact_phone;
            $base['total_deliveries']        = $agent->total_deliveries;
        }

        return $base;
    }
}
