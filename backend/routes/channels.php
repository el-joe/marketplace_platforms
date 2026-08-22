<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Each guard gets its own private channel namespace. The broadcasting/auth
| endpoint on each panel's subdomain is protected by that panel's guard
| middleware, so $user here will always be the correct model instance.
|
*/

Broadcast::channel('admin.{adminId}', function ($user, $adminId) {
    return (int) $user->id === (int) $adminId;
});

Broadcast::channel('vendor.{vendorAdminId}', function ($user, $vendorAdminId) {
    return (int) $user->id === (int) $vendorAdminId;
});

Broadcast::channel('delivery-agent.{agentId}', function ($user, $agentId) {
    return (int) $user->id === (int) $agentId;
});

Broadcast::channel('carrier-supervisor.{supervisorId}', function ($user, $supervisorId) {
    return (int) $user->id === (int) $supervisorId;
});

Broadcast::channel('travel-agency.{agencyId}', function ($user, $agencyId) {
    return (int) $user->id === (int) $agencyId;
});
