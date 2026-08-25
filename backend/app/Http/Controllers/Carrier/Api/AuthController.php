<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const ACCESS_TTL_MINUTES  = 60;
    private const REFRESH_TTL_MINUTES = 43200; // 30 days

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return ApiResponse::error('Too many login attempts. Try again in ' . $seconds . ' seconds.', [], 429);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = auth('shipping_supervisor_api')->setTTL(self::ACCESS_TTL_MINUTES)->attempt($credentials)) {
            RateLimiter::hit($throttleKey, 900);

            return ApiResponse::error('Invalid credentials.', [], 401);
        }

        RateLimiter::clear($throttleKey);

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        if (!$supervisor->is_active) {
            auth('shipping_supervisor_api')->logout();

            return ApiResponse::error('Your account is inactive. Contact your company administrator.', [], 403);
        }

        if ($request->filled('fcm_token') && $request->filled('platform')) {
            DeviceToken::updateOrCreate(
                [
                    'tokenable_type' => ShippingCompanySupervisor::class,
                    'tokenable_id'   => $supervisor->id,
                ],
                [
                    'token'        => $request->input('fcm_token'),
                    'platform'     => $request->input('platform'),
                    'is_active'    => 1,
                    'last_used_at' => now(),
                ]
            );
        }

        $supervisor->load(['company', 'country']);

        return ApiResponse::success(array_merge(
            ['supervisor' => $this->profile($supervisor)],
            $this->issueTokenPair($supervisor, $token)
        ));
    }

    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('shipping_supervisor_api')->refresh();
        } catch (\Throwable) {
            return ApiResponse::error('Invalid or expired refresh token.', [], 401);
        }

        return ApiResponse::success([
            'access_token' => $newToken,
            'token_type'   => 'bearer',
            'expires_in'   => self::ACCESS_TTL_MINUTES * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('shipping_supervisor_api')->logout();

        return ApiResponse::success(null, 'Logged out.');
    }

    public function me(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $supervisor->load(['company', 'country']);

        return ApiResponse::success(['supervisor' => $this->profile($supervisor)]);
    }

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => ShippingCompanySupervisor::class,
                'tokenable_id'   => $supervisor->id,
            ],
            [
                'token'        => $request->input('token'),
                'platform'     => $request->input('platform'),
                'is_active'    => 1,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, 'Device registered.');
    }

    public function removeDeviceToken(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        DeviceToken::where('tokenable_type', ShippingCompanySupervisor::class)
            ->where('tokenable_id', $supervisor->id)
            ->update(['is_active' => 0]);

        return ApiResponse::success(null, 'Device token removed.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function issueTokenPair(ShippingCompanySupervisor $supervisor, string $accessToken): array
    {
        JWTAuth::factory()->setTTL(self::REFRESH_TTL_MINUTES);

        $refreshToken = JWTAuth::customClaims([
            'type'  => 'refresh',
            'guard' => 'shipping_supervisor_api',
        ])->fromUser($supervisor);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => self::ACCESS_TTL_MINUTES * 60,
        ];
    }

    private function profile(ShippingCompanySupervisor $supervisor): array
    {
        return [
            'id'                          => $supervisor->id,
            'name'                        => $supervisor->name,
            'email'                       => $supervisor->email,
            'phone'                       => $supervisor->phone,
            'is_active'                   => $supervisor->is_active,
            'permissions'                 => $supervisor->permissions ?? [],
            'is_owner'                    => $supervisor->hasPermission('manage_agents'),
            'receives_all_notifications'  => $supervisor->receives_all_notifications,
            'company'                     => $supervisor->company ? [
                'id'     => $supervisor->company->id,
                'name'   => $supervisor->company->name,
                'status' => $supervisor->company->status?->value,
            ] : null,
            'country' => $supervisor->country ? [
                'id'   => $supervisor->country->id,
                'name' => $supervisor->country->name_en,
            ] : null,
        ];
    }
}
