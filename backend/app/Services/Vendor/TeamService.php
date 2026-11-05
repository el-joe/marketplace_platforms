<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Auth\CredentialDeliveryResult;
use App\Services\Auth\CredentialDeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TeamService
{
    public function __construct(private readonly CredentialDeliveryService $credentials) {}

    /**
     * Create a team member with an unusable password and email them a single-use,
     * time-limited set-password link. The account is kept even when the email fails
     * (the warning is returned so the inviter can retry; the member can also use
     * "Forgot password" on the login page).
     *
     * @param  array{name: string, email: string, role: string}  $data  an existing vendor-guard role name, or a short name (`manager`) that gets the `vendor_` prefix
     * @return array{0: VendorAdmin, 1: CredentialDeliveryResult}
     */
    public function invite(Vendor $vendor, array $data): array
    {
        $roleName = Role::where('name', $data['role'])->where('guard_name', 'vendor')->exists()
            ? $data['role']
            : 'vendor_'.$data['role'];

        $member = DB::transaction(function () use ($vendor, $data, $roleName) {
            $member = VendorAdmin::create([
                'vendor_id' => $vendor->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::password(32)),
                'role' => $roleName,
                'is_active' => true,
            ]);

            $member->assignRole($roleName);

            return $member;
        });

        return [$member, $this->sendInvite($member, $vendor)];
    }

    /**
     * Email the set-password invitation for an existing team member.
     */
    public function sendInvite(VendorAdmin $member, ?Vendor $vendor = null): CredentialDeliveryResult
    {
        $vendor ??= $member->vendor;

        $roleLabel = __('mail.credentials.roles.'.$member->role, [], 'en');
        if ($roleLabel === 'mail.credentials.roles.'.$member->role) {
            $roleLabel = Str::headline(str_replace('vendor_', '', (string) $member->role));
        } else {
            $roleLabel .= ' / '.__('mail.credentials.roles.'.$member->role, [], 'ar');
        }

        return $this->credentials->sendSetPasswordLink(
            $member,
            'vendor_admins',
            'vendor_team_invite',
            'vendor_team_invite',
            ['store' => (string) ($vendor?->store_name ?? $vendor?->business_name ?? ''), 'role' => $roleLabel],
        );
    }

    public function canModify(VendorAdmin $actor, VendorAdmin $target): bool
    {
        // Nobody can modify an owner row (except the owner themselves changing
        // their own profile — that goes through a separate profile endpoint).
        if ($target->isOwner()) {
            return false;
        }

        return $actor->can('team.manage');
    }
}
