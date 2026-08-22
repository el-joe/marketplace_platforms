<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamService
{
    public function invite(Vendor $vendor, array $data): VendorAdmin
    {
        $tempPassword = Str::random(16);

        $roleName = 'vendor_' . $data['role'];

        $member = VendorAdmin::create([
            'vendor_id' => $vendor->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($tempPassword),
            'role'      => $roleName,
            'is_active' => true,
        ]);

        $member->assignRole($roleName);

        // Queue invite email with password-set link.
        // SendVendorTeamInviteJob::dispatch($member, $tempPassword);

        return $member;
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
