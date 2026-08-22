<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Enums\ShippingCompanyStatus;
use App\Http\Requests\Carrier\Auth\LoginRequest;
use App\Http\Resources\Carrier\ShippingCompanyResource;
use App\Http\Resources\Carrier\SupervisorResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const ACCESS_TTL_MINUTES  = 60;
    private const REFRESH_TTL_MINUTES = 43200; // 30 days

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var ShippingCompanySupervisor|null $supervisor */
        $supervisor = ShippingCompanySupervisor::where('email', $request->email)->first();

        if (! $supervisor || ! Hash::check($request->password, $supervisor->password)) {
            return ApiResponse::error(__('carrier.api.invalid_credentials'), [], 401);
        }

        // Company-level gate: check before is_active so company suspension
        // gives a clearer message than a generic "account inactive" response.
        $company = $supervisor->company;

        if ($company->status === ShippingCompanyStatus::Pending) {
            return ApiResponse::error(
                __('carrier.api.company_pending'),
                ['company_status' => 'pending'],
                403
            );
        }

        if ($company->status === ShippingCompanyStatus::Suspended) {
            return ApiResponse::error(
                __('carrier.api.company_suspended'),
                ['company_status' => 'suspended'],
                403
            );
        }

        if (! $supervisor->is_active) {
            return ApiResponse::error(
                __('carrier.api.account_inactive'),
                [],
                403
            );
        }

        $supervisor->load('company.country');

        if ($request->filled('fcm_token') && $request->filled('platform')) {
            DeviceToken::updateOrCreate(
                [
                    'tokenable_type' => ShippingCompanySupervisor::class,
                    'tokenable_id'   => $supervisor->id,
                ],
                [
                    'token'        => $request->fcm_token,
                    'platform'     => $request->platform,
                    'is_active'    => 1,
                    'last_used_at' => now(),
                ]
            );
        }

        return ApiResponse::success(array_merge(
            [
                'supervisor'  => new SupervisorResource($supervisor),
                'company'     => new ShippingCompanyResource($supervisor->company),
                'permissions' => $supervisor->permissions ?? [],
            ],
            $this->issueTokenPair($supervisor)
        ));
    }

    // ── Device Token (FCM) ───────────────────────────────────────────────────

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
                'token'        => $request->token,
                'platform'     => $request->platform,
                'is_active'    => 1,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, __('carrier.api.device_registered'));
    }

    public function removeDeviceToken(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();

        DeviceToken::where('tokenable_type', ShippingCompanySupervisor::class)
            ->where('tokenable_id', $supervisor->id)
            ->update(['is_active' => 0]);

        return ApiResponse::success(null, __('carrier.api.device_token_removed'));
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(): JsonResponse
    {
        auth('shipping_supervisor_api')->logout();
        return ApiResponse::success(null, __('carrier.api.logged_out'));
    }

    // ── Refresh Token ─────────────────────────────────────────────────────────

    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('shipping_supervisor_api')->refresh();
        } catch (\Throwable) {
            return ApiResponse::error(__('carrier.api.invalid_refresh_token'), [], 401);
        }

        return ApiResponse::success([
            'access_token' => $newToken,
            'token_type'   => 'bearer',
            'expires_in'   => self::ACCESS_TTL_MINUTES * 60,
        ]);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function me(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $supervisor->load('company.country');

        return ApiResponse::success([
            'supervisor'  => new SupervisorResource($supervisor),
            'company'     => new ShippingCompanyResource($supervisor->company),
            'permissions' => $supervisor->permissions ?? [],
        ]);
    }

    // ── Token helpers ─────────────────────────────────────────────────────────

    private function issueTokenPair(ShippingCompanySupervisor $supervisor): array
    {
        $accessToken = auth('shipping_supervisor_api')->setTTL(self::ACCESS_TTL_MINUTES)->login($supervisor);

        JWTAuth::factory()->setTTL(self::REFRESH_TTL_MINUTES);

        $refreshToken = JWTAuth::customClaims([
            'type'  => 'refresh',
            'guard' => 'shipping_supervisor_api',
        ])->setTTL(self::REFRESH_TTL_MINUTES)->fromUser($supervisor);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => self::ACCESS_TTL_MINUTES * 60,
        ];
    }
}
