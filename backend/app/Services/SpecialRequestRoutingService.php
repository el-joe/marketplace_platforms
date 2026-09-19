<?php

namespace App\Services;

use App\Models\CustomerSpecialRequest;
use App\Models\MarketerProfile;
use App\Notifications\Marketer\BrokerSpecialRequestNotification;

class SpecialRequestRoutingService
{
    /**
     * Notify active affiliate brokers matching the request (see
     * MarketerProfile::scopeMatchingRequest). Each marketer-admin is notified once.
     *
     * @return int number of brokers (profiles) matched; stored as brokers_notified
     */
    public function notifyMatchingBrokers(CustomerSpecialRequest $request): int
    {
        $request->loadMissing('city');

        $profiles = MarketerProfile::query()
            ->matchingRequest($request)
            ->with('marketer.marketerAdmins')
            ->get();

        $admins = $profiles
            ->flatMap(fn ($p) => $p->marketer?->marketerAdmins ?? [])
            ->unique('id');

        foreach ($admins as $admin) {
            $admin->notify(new BrokerSpecialRequestNotification($request, $admin->id));
        }

        $brokers = $profiles->count();
        $request->update(['brokers_notified' => $brokers]);

        return $brokers;
    }
}
