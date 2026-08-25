<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TravelAgencyPortal\Fcm\RegisterDeviceRequest;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\TravelAgency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FcmDeviceController extends Controller
{
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        /** @var TravelAgency $agency */
        $agency = Auth::guard('travel_agencies')->user();

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => TravelAgency::class,
                'tokenable_id' => $agency->id,
            ],
            [
                'token' => $request->token,
                'platform' => $request->platform,
                'is_active' => 1,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, 'Device registered for push notifications.');
    }

    public function unregister(): JsonResponse
    {
        /** @var TravelAgency $agency */
        $agency = Auth::guard('travel_agencies')->user();

        DeviceToken::where('tokenable_type', TravelAgency::class)
            ->where('tokenable_id', $agency->id)
            ->update(['is_active' => 0]);

        return ApiResponse::success(null, 'Device token unregistered.');
    }
}
