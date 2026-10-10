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
}, ['guards' => ['admin']]);

Broadcast::channel('vendor.{vendorAdminId}', function ($user, $vendorAdminId) {
    return (string) $user->id === (string) $vendorAdminId;
}, ['guards' => ['vendor']]);

Broadcast::channel('delivery-agent.{agentId}', function ($user, $agentId) {
    return (string) $user->id === (string) $agentId;
}, ['guards' => ['delivery']]);

Broadcast::channel('carrier-supervisor.{supervisorId}', function ($user, $supervisorId) {
    return (string) $user->id === (string) $supervisorId;
}, ['guards' => ['shipping_supervisor']]);

Broadcast::channel('travel-agency.{agencyId}', function ($user, $agencyId) {
    return (string) $user->id === (string) $agencyId;
}, ['guards' => ['travel_agency']]);

Broadcast::channel('marketer.{marketerAdminId}', function ($user, $marketerAdminId) {
    return (string) $user->id === (string) $marketerAdminId;
}, ['guards' => ['marketer']]);

Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
    return (string) $user->id === (string) $customerId;
}, ['guards' => ['customer']]);
