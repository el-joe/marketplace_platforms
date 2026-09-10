<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PaidAdSlot;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdSlotPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermissionTo('ad_slots.view');
    }

    public function view(Admin $admin, PaidAdSlot $adSlot): bool
    {
        return $admin->hasPermissionTo('ad_slots.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasPermissionTo('ad_slots.create');
    }

    public function update(Admin $admin, PaidAdSlot $adSlot): bool
    {
        return $admin->hasPermissionTo('ad_slots.edit');
    }

    public function delete(Admin $admin, PaidAdSlot $adSlot): bool
    {
        return $admin->hasPermissionTo('ad_slots.delete');
    }
}
