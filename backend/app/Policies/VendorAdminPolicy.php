<?php

namespace App\Policies;

use App\Models\VendorAdmin;

class VendorAdminPolicy
{
    public function manageTeam(VendorAdmin $actor): bool
    {
        return $actor->can('team.manage');
    }

    public function mutateTeamMember(VendorAdmin $actor, VendorAdmin $target): bool
    {
        if ($target->isOwner()) {
            return false;
        }

        return $actor->can('team.manage');
    }
}
