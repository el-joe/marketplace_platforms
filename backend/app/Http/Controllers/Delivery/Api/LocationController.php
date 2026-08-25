<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\Location\UpdateLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Services\Delivery\LocationTrackingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class LocationController extends Controller
{
    public function __construct(private readonly LocationTrackingService $locationService) {}

    public function update(UpdateLocationRequest $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        try {
            $this->locationService->update($agent, $request->float('latitude'), $request->float('longitude'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 429);
        }

        return ApiResponse::success([
            'latitude'    => $agent->current_latitude,
            'longitude'   => $agent->current_longitude,
            'recorded_at' => $agent->last_location_at?->toIso8601String(),
        ], __('delivery.messages.location.updated'));
    }
}
