<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Team\InviteTeamMemberRequest;
use App\Http\Resources\Vendor\TeamMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Vendor\TeamService;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $team) {}

    private function vendor(): Vendor
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();
        return $admin->vendor;
    }

    private function actor(): VendorAdmin
    {
        /** @var VendorAdmin $admin */
        return auth('vendor')->user();
    }

    // GET /team
    public function index(): JsonResponse
    {
        $members = $this->vendor()->vendorAdmins()->get();
        return ApiResponse::success(TeamMemberResource::collection($members));
    }

    // POST /team
    public function store(InviteTeamMemberRequest $request): JsonResponse
    {
        if (!$this->actor()->can('team.invite')) {
            return ApiResponse::error('You do not have permission to invite team members.', [], 403);
        }

        $member = $this->team->invite($this->vendor(), $request->validated());

        return ApiResponse::success(new TeamMemberResource($member), 'Team member invited.', 201);
    }

    // PUT /team/{id}/toggle-active
    public function toggleActive(string $id): JsonResponse
    {
        $target = VendorAdmin::where('vendor_id', $this->vendor()->id)->findOrFail($id);

        if ($target->isOwner()) {
            return ApiResponse::error('Cannot deactivate the store owner.', [], 403);
        }

        if (!$this->team->canModify($this->actor(), $target)) {
            return ApiResponse::error('Unauthorized.', [], 403);
        }

        $target->update(['is_active' => !$target->is_active]);

        return ApiResponse::success(new TeamMemberResource($target->fresh()), 'Status updated.');
    }

    // DELETE /team/{id}
    public function destroy(string $id): JsonResponse
    {
        $target = VendorAdmin::where('vendor_id', $this->vendor()->id)->findOrFail($id);

        if ($target->isOwner()) {
            return ApiResponse::error('Cannot remove the store owner.', [], 403);
        }

        if (!$this->team->canModify($this->actor(), $target)) {
            return ApiResponse::error('Unauthorized.', [], 403);
        }

        $target->delete();

        return ApiResponse::success(null, 'Team member removed.');
    }
}
