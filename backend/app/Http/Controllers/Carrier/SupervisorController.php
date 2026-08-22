<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrier\Supervisor\StoreSupervisorRequest;
use App\Http\Requests\Carrier\Supervisor\UpdateSupervisorRequest;
use App\Http\Resources\Carrier\SupervisorResource;
use App\Http\Responses\ApiResponse;
use App\Models\ShippingCompanySupervisor;
use App\Services\Carrier\SupervisorService;
use Illuminate\Http\JsonResponse;

class SupervisorController extends Controller
{
    public function __construct(private readonly SupervisorService $supervisorService) {}

    // ── GET /supervisors ──────────────────────────────────────────────────────
    // Permission: manage_agents
    // // VERIFY: The schema has no "manage_supervisors" permission. The web portal
    // uses manage_agents as the owner-gate for all supervisor CRUD. Following the
    // same convention here until a distinct permission is added.

    public function index(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $caller */
        $caller = auth('shipping_supervisor_api')->user();

        $supervisors = ShippingCompanySupervisor::where('shipping_company_id', $caller->shipping_company_id)
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success(SupervisorResource::collection($supervisors)->resolve());
    }

    // ── POST /supervisors ─────────────────────────────────────────────────────
    // Permission: manage_agents

    public function store(StoreSupervisorRequest $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $caller */
        $caller     = auth('shipping_supervisor_api')->user();
        $company    = $caller->company;

        $supervisor = $this->supervisorService->invite($company, $request->validated());

        return ApiResponse::success(new SupervisorResource($supervisor), __('carrier.api.supervisor_invited'), 201);
    }

    // ── PUT /supervisors/{id} ─────────────────────────────────────────────────
    // Permission: manage_agents

    public function update(UpdateSupervisorRequest $request, string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $caller */
        $caller     = auth('shipping_supervisor_api')->user();
        $supervisor = ShippingCompanySupervisor::findOrFail($id);

        $this->authorize('update', $supervisor);

        $data = $request->validated();

        // Gate: receives_all_notifications can only be set true if the company allows it.
        if (isset($data['receives_all_notifications']) && $data['receives_all_notifications'] === true) {
            if (! $this->supervisorService->canReceiveAllNotifications($caller->company)) {
                return ApiResponse::error(
                    __('carrier.api.notifications_feature_disabled'),
                    [],
                    403
                );
            }
        }

        $supervisor->update($data);

        return ApiResponse::success(new SupervisorResource($supervisor->fresh()), __('carrier.api.supervisor_updated'));
    }

    // ── DELETE /supervisors/{id} ──────────────────────────────────────────────
    // Permission: manage_agents

    public function destroy(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $caller */
        $caller     = auth('shipping_supervisor_api')->user();
        $supervisor = ShippingCompanySupervisor::findOrFail($id);

        $this->authorize('delete', $supervisor);

        $supervisor->delete();

        return ApiResponse::success(null, __('carrier.api.supervisor_removed'));
    }
}
