<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Register a push notification device token for the authenticated customer.
     *
     * POST /v1/device-tokens
     * Auth: required (auth:customer)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'    => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray(), 422);
        }

        $customer = auth('customer')->user();
        $data     = $validator->validated();

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => $customer::class,
                'tokenable_id'   => $customer->getKey(),
                'token'          => $data['token'],
            ],
            [
                'platform'     => $data['platform'],
                'is_active'    => true,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, __('customer_api.notification.device_registered'));
    }
}
