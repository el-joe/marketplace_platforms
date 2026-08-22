<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\SupportTicket;

class SupportTicketPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermissionTo('support.view');
    }

    public function view(Admin $admin, SupportTicket $ticket): bool
    {
        return $admin->hasPermissionTo('support.view');
    }

    public function reply(Admin $admin, SupportTicket $ticket): bool
    {
        return $admin->hasPermissionTo('support.reply');
    }

    public function assign(Admin $admin, SupportTicket $ticket): bool
    {
        return $admin->hasPermissionTo('support.assign');
    }

    public function updateStatus(Admin $admin, SupportTicket $ticket): bool
    {
        return $admin->hasPermissionTo('support.reply');
    }
}
