<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Enums\DeliveryAgentShiftStatus;
use App\Enums\DeliveryAgentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\Auth\LoginRequest;
use App\Http\Resources\Delivery\DeliveryAgentProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentShift;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const ACCESS_TTL_MINUTES  = 60;
    private const REFRESH_TTL_MINUTES = 43200; // 30 days

    public function login(LoginRequest $request): JsonResponse
    {
        $credential = $request->email_or_phone;

        /** @var DeliveryAgent|null $agent */
        $agent = DeliveryAgent::where('email', $credential)
            ->orWhere('phone', $credential)
            ->first();

        if (!$agent || !Hash::check($request->password, $agent->password)) {
            return ApiResponse::error(__('delivery.messages.auth.invalid_credentials'), [], 401);
        }

        if (in_array($agent->status, [DeliveryAgentStatus::Suspended, DeliveryAgentStatus::Inactive], true)) {
            $message = $agent->status === DeliveryAgentStatus::Suspended
                ? __('delivery.messages.auth.account_suspended')
                : __('delivery.messages.auth.account_not_active');

            return ApiResponse::error($message, ['status' => $agent->status?->value], 403);
        }

        $agent->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $agent->load(['zone', 'country', 'shippingCompany']);

        if ($request->filled('fcm_token') && $request->filled('platform')) {
            DeviceToken::updateOrCreate(
                [
                    'tokenable_type' => DeliveryAgent::class,
                    'tokenable_id'   => $agent->id,
                ],
                [
                    'token'        => $request->fcm_token,
                    'platform'     => $request->platform,
                    'is_active'    => 1,
                    'last_used_at' => now(),
                ]
            );
        }

        $todayStats = $this->todayStats($agent);

        $resource = new DeliveryAgentProfileResource($agent);
        $resource->todayStats = $todayStats;

        return ApiResponse::success(array_merge(
            ['agent' => $resource],
            $this->issueTokenPair($agent)
        ));
    }

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string|max:255',
            'platform' => 'required|string|in:ios,android',
        ]);

        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => DeliveryAgent::class,
                'tokenable_id'   => $agent->id,
            ],
            [
                'token'        => $request->token,
                'platform'     => $request->platform,
                'is_active'    => 1,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, __('delivery.messages.auth.device_registered'));
    }

    public function removeDeviceToken(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        DeviceToken::where('tokenable_type', DeliveryAgent::class)
            ->where('tokenable_id', $agent->id)
            ->update(['is_active' => 0]);

        return ApiResponse::success(null, __('delivery.messages.auth.device_token_removed'));
    }

    public function logout(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        // Force agent offline on logout — they shouldn't appear available after closing the app
        if ($agent->status === DeliveryAgentStatus::OnShift) {
            $agent->update(['status' => DeliveryAgentStatus::Active, 'is_available' => false]);

            DeliveryAgentShift::where('agent_id', $agent->id)
                ->where('status', DeliveryAgentShiftStatus::Active)
                ->whereDate('shift_date', now()->toDateString())
                ->update(['actual_end' => now(), 'status' => DeliveryAgentShiftStatus::Completed]);
        } else {
            $agent->update(['is_available' => false]);
        }

        auth('delivery_api')->logout();

        return ApiResponse::success(null, __('delivery.messages.auth.logged_out'));
    }

    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('delivery_api')->refresh();
        } catch (\Throwable) {
            return ApiResponse::error(__('delivery.messages.auth.invalid_refresh_token'), [], 401);
        }

        return ApiResponse::success([
            'access_token' => $newToken,
            'token_type'   => 'bearer',
            'expires_in'   => self::ACCESS_TTL_MINUTES * 60,
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->load(['zone', 'country', 'shippingCompany']);

        $resource = new DeliveryAgentProfileResource($agent);
        $resource->todayStats = $this->todayStats($agent);

        return ApiResponse::success(['agent' => $resource]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function issueTokenPair(DeliveryAgent $agent): array
    {
        $accessToken = auth('delivery_api')->setTTL(self::ACCESS_TTL_MINUTES)->login($agent);

        JWTAuth::factory()->setTTL(self::REFRESH_TTL_MINUTES);
        
        $refreshToken = JWTAuth::customClaims([
            'type'  => 'refresh',
            'guard' => 'delivery_api',
        ])->fromUser($agent);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => self::ACCESS_TTL_MINUTES * 60,
        ];
    }

    private function todayStats(DeliveryAgent $agent): array
    {
        $todayShift = DeliveryAgentShift::where('agent_id', $agent->id)
            ->whereDate('shift_date', now()->toDateString())
            ->latest()
            ->first();

        return [
            'deliveries_today' => $todayShift?->total_deliveries ?? 0,
            'earnings_today'   => (int) ($todayShift?->getRawOriginal('total_earnings') ?? 0),
            'shift_started_at' => $todayShift?->actual_start?->toIso8601String(),
            'currency'         => $agent->country?->currency_code
                ?? $agent->zone?->country?->currency_code
                ?? 'AED',
        ];
    }
}
