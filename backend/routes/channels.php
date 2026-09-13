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
    return (string) $user->id === (string) $adminId;
});

Broadcast::channel('vendor.{vendorAdminId}', function ($user, $vendorAdminId) {
    return (string) $user->id === (string) $vendorAdminId;
});

Broadcast::channel('delivery-agent.{agentId}', function ($user, $agentId) {
    return (string) $user->id === (string) $agentId;
});

Broadcast::channel('carrier-supervisor.{supervisorId}', function ($user, $supervisorId) {
    return (string) $user->id === (string) $supervisorId;
});

Broadcast::channel('travel-agency.{agencyId}', function ($user, $agencyId) {
    return (string) $user->id === (string) $agencyId;
});

Broadcast::channel('marketer.{marketerAdminId}', function ($user, $marketerAdminId) {
    return (string) $user->id === (string) $marketerAdminId;
});
