<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PaidAdBooking;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaidAdBookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermissionTo('ad_bookings.view');
    }

    public function view(Admin $admin, PaidAdBooking $paidAdBooking): bool
    {
        return $admin->hasPermissionTo('ad_bookings.view');
    }

    public function review(Admin $admin, PaidAdBooking $paidAdBooking): bool
    {
        return $admin->hasPermissionTo('ad_bookings.review');
    }

    public function manage(Admin $admin, PaidAdBooking $paidAdBooking): bool
    {
        return $admin->hasPermissionTo('ad_bookings.manage');
    }

    public function finance(Admin $admin, PaidAdBooking $paidAdBooking): bool
    {
        return $admin->hasPermissionTo('ad_bookings.finance');
    }
}
