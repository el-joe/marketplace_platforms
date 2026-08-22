<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\AgentLocationHistory;
use App\Models\DeliveryAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LocationController extends Controller
{
    /**
     * Update agent's current location.
     * Rate-limited to 1 update per 30 seconds per agent.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        $key = 'agent-location:' . $agent->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => __('delivery.messages.location.please_wait_seconds', ['seconds' => $seconds]),
            ], 429);
        }

        RateLimiter::hit($key, 30);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $now = now();

        $agent->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'last_location_at' => $now,
        ]);

        // Append to location history (no updated_at — uses recorded_at)
        AgentLocationHistory::create([
            'agent_id' => $agent->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => $now,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Toggle agent availability.
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = Auth::guard('delivery')->user();

        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $agent->update(['is_available' => $validated['is_available']]);

        return response()->json([
            'success' => true,
            'is_available' => $agent->is_available,
            'message' => $agent->is_available
                ? __('delivery.messages.location.now_available')
                : __('delivery.messages.location.now_offline'),
        ]);
    }
}
