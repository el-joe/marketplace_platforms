<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\TeamMemberInviteMail;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    private function admin(): VendorAdmin
    {
        return Auth::guard('vendor_api')->user();
    }

    private function vendorId(): string
    {
        return $this->admin()->vendor_id;
    }

    private function authoriseMember(VendorAdmin $member): void
    {
        if ($member->vendor_id !== $this->vendorId()) {
            abort(404);
        }
    }

    /** GET /api/partner/v1/team */
    public function index(): JsonResponse
    {
        $members = VendorAdmin::where('vendor_id', $this->vendorId())
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_owner', 'is_active', 'last_login_at', 'created_at']);

        return ApiResponse::success($members);
    }

    /** POST /api/partner/v1/team */
    public function store(Request $request): JsonResponse
    {
        $admin = $this->admin();

        if (! $admin->isOwner()) {
            return response()->json(['success' => false, 'message' => 'Only the owner may add team members.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:vendor_admins,email'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'vendor'), Rule::notIn(['vendor_owner'])],
        ]);

        $tempPassword = Str::password(12, symbols: false);

        $member = DB::transaction(function () use ($validated, $tempPassword) {
            $member = VendorAdmin::create([
                'vendor_id' => $this->vendorId(),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'role' => $validated['role'],
                'is_active' => true,
            ]);

            $member->assignRole($validated['role']);

            return $member;
        });

        Mail::to($member->email)->send(new TeamMemberInviteMail($member, $tempPassword));

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully.',
            'data' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'is_active' => $member->is_active,
                'last_login_at' => null,
            ],
        ], 201);
    }

    /** PUT /api/partner/v1/team/{memberId} */
    public function update(Request $request, string $memberId): JsonResponse
    {
        $admin = $this->admin();
        $member = VendorAdmin::findOrFail($memberId);

        $this->authoriseMember($member);

        if (! $admin->isOwner()) {
            return response()->json(['success' => false, 'message' => 'Only the owner may update team members.'], 403);
        }

        if ($member->id === $admin->id) {
            return response()->json(['success' => false, 'message' => 'You cannot edit your own role.'], 422);
        }

        if ($member->isOwner()) {
            return response()->json(['success' => false, 'message' => 'Owner role cannot be changed.'], 422);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'vendor'), Rule::notIn(['vendor_owner'])],
        ]);

        DB::transaction(function () use ($member, $validated) {
            $member->syncRoles([$validated['role']]);
            $member->update(['role' => $validated['role']]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Member role updated successfully.',
            'role' => $member->role,
        ]);
    }

    /** DELETE /api/partner/v1/team/{memberId} */
    public function destroy(string $memberId): JsonResponse
    {
        $admin = $this->admin();
        $member = VendorAdmin::findOrFail($memberId);

        $this->authoriseMember($member);

        if (! $admin->isOwner()) {
            return response()->json(['success' => false, 'message' => 'Only the owner may remove team members.'], 403);
        }

        if ($member->isOwner()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete the owner account.'], 422);
        }

        if ($member->id === $admin->id) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 422);
        }

        $member->delete();

        return response()->json(['success' => true, 'message' => 'Team member removed.']);
    }
}
