<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ShippingCompanySupervisor;
use App\Services\Carrier\SupervisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    private const ALL_PERMISSIONS = ['manage_agents', 'view_orders', 'assign_orders', 'view_reports'];

    public function __construct(private readonly SupervisorService $supervisorService)
    {
    }

    public function index(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requireOwner($supervisor);

        $supervisors = ShippingCompanySupervisor::where('shipping_company_id', $supervisor->shipping_company_id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($supervisors->items())->map(fn (ShippingCompanySupervisor $s) => $this->transform($s)),
                'meta' => [
                    'current_page' => $supervisors->currentPage(),
                    'last_page'    => $supervisors->lastPage(),
                    'per_page'     => $supervisors->perPage(),
                    'total'        => $supervisors->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requireOwner($supervisor);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:shipping_company_supervisors,email'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'country_id'     => ['nullable', 'exists:countries,id'],
            'permissions'    => ['required', 'array'],
            'permissions.*'  => ['in:' . implode(',', self::ALL_PERMISSIONS)],
        ]);

        $newSupervisor = $this->supervisorService->invite($supervisor->company, $data);

        return ApiResponse::success(['supervisor' => $this->transform($newSupervisor)], 'Supervisor invited.', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requireOwner($supervisor);

        $target = $this->findSupervisor($supervisor, $id);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'permissions'    => ['required', 'array'],
            'permissions.*'  => ['in:' . implode(',', self::ALL_PERMISSIONS)],
            'is_active'      => ['boolean'],
        ]);

        $target->update($data);

        return ApiResponse::success(['supervisor' => $this->transform($target->fresh())], 'Supervisor updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $this->requireOwner($supervisor);

        $target = $this->findSupervisor($supervisor, $id);

        abort_if($target->id === $supervisor->id, 403, 'You cannot delete your own account.');

        $target->delete();

        return ApiResponse::success(null, 'Supervisor deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function requireOwner(ShippingCompanySupervisor $supervisor): void
    {
        abort_unless($supervisor->hasPermission('manage_agents'), 403, 'Only company owners can manage supervisors.');
    }

    private function findSupervisor(ShippingCompanySupervisor $supervisor, string $id): ShippingCompanySupervisor
    {
        $target = ShippingCompanySupervisor::where('id', $id)
            ->where('shipping_company_id', $supervisor->shipping_company_id)
            ->firstOrFail();

        return $target;
    }

    private function transform(ShippingCompanySupervisor $s): array
    {
        return [
            'id'                          => $s->id,
            'name'                        => $s->name,
            'email'                       => $s->email,
            'phone'                       => $s->phone,
            'is_active'                   => $s->is_active,
            'permissions'                 => $s->permissions ?? [],
            'is_owner'                    => $s->hasPermission('manage_agents'),
            'receives_all_notifications'  => $s->receives_all_notifications,
            'created_at'                  => $s->created_at?->toIso8601String(),
        ];
    }
}
