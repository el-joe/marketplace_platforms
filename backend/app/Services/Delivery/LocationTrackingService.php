<?php

namespace App\Services\Delivery;

use App\Models\AgentLocationHistory;
use App\Models\DeliveryAgent;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class LocationTrackingService
{
    private const THROTTLE_SECONDS = 30;

    public function update(DeliveryAgent $agent, float $lat, float $lng): void
    {
        $key = 'delivery_location:' . $agent->id;

        if (Cache::has($key)) {
            $ttl = Cache::get($key . ':expires_at') - time();
            throw new RuntimeException(
                "Location update rate limit: please wait {$ttl} second(s) before updating again."
            );
        }

        Cache::put($key, 1, self::THROTTLE_SECONDS);
        Cache::put($key . ':expires_at', time() + self::THROTTLE_SECONDS, self::THROTTLE_SECONDS);

        $agent->update([
            'current_latitude'  => $lat,
            'current_longitude' => $lng,
            'last_location_at'  => now(),
        ]);

        AgentLocationHistory::create([
            'agent_id'    => $agent->id,
            'latitude'    => $lat,
            'longitude'   => $lng,
            'recorded_at' => now(),
        ]);
    }
}
