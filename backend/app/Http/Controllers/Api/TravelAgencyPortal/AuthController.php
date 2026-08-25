<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelAgencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TravelAgencyPortal\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\TravelAgencyPortal\Auth\LoginRequest;
use App\Http\Requests\Api\TravelAgencyPortal\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\TravelAgencyPortal\TravelAgencyProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\TravelAgency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const ACCESS_TTL_MINUTES = 60;

    private const REFRESH_TTL_MINUTES = 43200; // 30 days

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var TravelAgency|null $agency */
        $agency = TravelAgency::where('email', $request->email)->first();

        if (! $agency || ! Hash::check($request->password, $agency->password)) {
            return ApiResponse::error('Invalid credentials.', [], 401);
        }

        if ($agency->status !== TravelAgencyStatus::Active) {
            $message = match ($agency->status) {
                TravelAgencyStatus::Pending => 'Your account is pending admin approval.',
                TravelAgencyStatus::Suspended => 'Your account has been suspended. Please contact support.',
                default => 'Your account is not active.',
            };

            return ApiResponse::error($message, ['status' => $agency->status->value], 403);
        }

        if ($request->filled('fcm_token') && $request->filled('platform')) {
            DeviceToken::updateOrCreate(
                ['tokenable_type' => TravelAgency::class, 'tokenable_id' => $agency->id],
                [
                    'token' => $request->fcm_token,
                    'platform' => $request->platform,
                    'is_active' => 1,
                    'last_used_at' => now(),
                ]
            );
        }

        $agency->load('country');

        return ApiResponse::success(array_merge(
            ['agency' => new TravelAgencyProfileResource($agency)],
            $this->issueTokenPair($agency)
        ));
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(): JsonResponse
    {
        auth('travel_agencies')->logout();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    // ── Refresh Token ─────────────────────────────────────────────────────────

    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('travel_agencies')->refresh();
        } catch (\Throwable) {
            return ApiResponse::error('Invalid or expired refresh token.', [], 401);
        }

        return ApiResponse::success([
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
        ]);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function me(): JsonResponse
    {
        /** @var TravelAgency $agency */
        $agency = auth('travel_agencies')->user();
        $agency->load('country');

        return ApiResponse::success(new TravelAgencyProfileResource($agency));
    }

    // ── Forgot Password ───────────────────────────────────────────────────────

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('travel_agencies')->sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponse::error(__($status), [], 422);
        }

        return ApiResponse::success(null, 'Password reset link sent.');
    }

    // ── Reset Password ────────────────────────────────────────────────────────

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('travel_agencies')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (TravelAgency $agency, string $password) {
                $agency->update(['password' => Hash::make($password)]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(__($status), [], 422);
        }

        return ApiResponse::success(null, 'Password reset successfully.');
    }

    // ── Token helpers ─────────────────────────────────────────────────────────

    private function issueTokenPair(TravelAgency $agency): array
    {
        $accessToken = auth('travel_agencies')->setTTL(self::ACCESS_TTL_MINUTES)->login($agency);

        JWTAuth::factory()->setTTL(self::REFRESH_TTL_MINUTES);

        $refreshToken = JWTAuth::customClaims([
            'type' => 'refresh',
            'guard' => 'travel_agencies',
        ])->setTTL(self::REFRESH_TTL_MINUTES)->fromUser($agency);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
        ];
    }
}
