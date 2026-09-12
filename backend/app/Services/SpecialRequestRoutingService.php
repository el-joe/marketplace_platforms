<?php

namespace App\Services;

use App\Models\CustomerSpecialRequest;
use App\Models\MarketerProfile;
use App\Notifications\Marketer\BrokerSpecialRequestNotification;

class SpecialRequestRoutingService
{
    /**
     * Notify affiliate ("broker") marketers whose specialization matches
     * the request's category + city, and record how many were notified.
     *
     * City matching: if the request has no city (customer is flexible),
     * every broker matching the category is notified. Otherwise, only
     * brokers serving that specific city or all cities are notified.
     */
    public function notifyMatchingBrokers(CustomerSpecialRequest $request): int
    {
        $brokers = MarketerProfile::query()
            ->whereHas('marketer', fn ($q) => $q->where('marketer_type', 'affiliate')->where('global_status', 'active'))
            ->where('broker_category_id', $request->category_id)
            ->when($request->city_id, function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('broker_serves_all_cities', true)
                       ->orWhere('broker_city_id', $request->city_id);
                });
            })
            ->with('marketer.marketerAdmins')
            ->get();

        $notified = 0;
        foreach ($brokers as $profile) {
            foreach ($profile->marketer->marketerAdmins as $marketerAdmin) {
                $marketerAdmin->notify(new BrokerSpecialRequestNotification($request, $marketerAdmin->id));
                $notified++;
            }
        }

        $request->update(['brokers_notified' => $notified]);

        return $notified;
    }
}
