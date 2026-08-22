<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Auth\ForgotPasswordRequest;
use App\Http\Requests\Vendor\Auth\LoginRequest;
use App\Http\Requests\Vendor\Auth\ResetPasswordRequest;
use App\Http\Resources\Vendor\VendorProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        /** @var VendorAdmin|null $admin */
        $admin = VendorAdmin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return ApiResponse::error('Invalid credentials.', [], 401);
        }

        if (!$admin->is_active) {
            return ApiResponse::error('Your account has been deactivated.', [], 403);
        }

        $vendor = $admin->vendor;

        if ($vendor && in_array($vendor->global_status, [\App\Enums\VendorGlobalStatus::Suspended, \App\Enums\VendorGlobalStatus::Blacklisted], true)) {
            return ApiResponse::error('Your store account has been suspended.', [], 403);
        }

        $admin->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        if ($request->filled('fcm_token') && $request->filled('platform')) {
            DeviceToken::updateOrCreate(
                [
                    'tokenable_type' => VendorAdmin::class,
                    'tokenable_id' => $admin->id,
                ],
                [
                    'token' => $request->fcm_token,
                    'platform' => $request->platform,
                    'is_active' => 1,
                    'last_used_at' => now(),
                ]
            );
        }

        return ApiResponse::success(array_merge(
            [
                'admin' => new \App\Http\Resources\Vendor\TeamMemberResource($admin),
                'vendor' => $vendor ? new VendorProfileResource($vendor) : null
            ],
            $this->issueTokenPair($admin)
        ));
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(): JsonResponse
    {
        auth('vendor')->logout();
        return ApiResponse::success(null, 'Logged out successfully.');
    }

    // ── Refresh ───────────────────────────────────────────────────────────────

    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('vendor')->refresh();
        } catch (\Throwable) {
            return ApiResponse::error('Invalid or expired refresh token.', [], 401);
        }

        return ApiResponse::success([
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
        ]);
    }

    // ── Device tokens (FCM) ──────────────────────────────────────────────────────

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:255',
            'platform' => 'required|string|in:ios,android',
        ]);

        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => VendorAdmin::class,
                'tokenable_id' => $admin->id,
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

    public function removeDeviceToken(): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();

        DeviceToken::where('tokenable_type', VendorAdmin::class)
            ->where('tokenable_id', $admin->id)
            ->update(['is_active' => 0]);

        return ApiResponse::success(null, 'Device token deregistered.');
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function me(): JsonResponse
    {
        /** @var VendorAdmin $admin */
        $admin = auth('vendor')->user();
        $vendor = $admin->vendor()->with('documents', 'bankAccounts')->first();

        return ApiResponse::success([
            'admin' => new \App\Http\Resources\Vendor\TeamMemberResource($admin),
            'vendor' => $vendor ? new VendorProfileResource($vendor) : null,
            'documents' => $vendor
                ? \App\Http\Resources\Vendor\SellerDocumentResource::collection($vendor->documents)
                : [],
        ]);
    }

    // ── Forgot Password ───────────────────────────────────────────────────────

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('vendor_admins')->sendResetLink(
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
        $status = Password::broker('vendor_admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (VendorAdmin $admin, string $password) {
                $admin->update(['password' => Hash::make($password)]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(__($status), [], 422);
        }

        return ApiResponse::success(null, 'Password reset successfully.');
    }

    // ── Token helpers ─────────────────────────────────────────────────────────

    private function issueTokenPair(VendorAdmin $admin): array
    {
        $accessToken = auth('vendor')->setTTL(self::ACCESS_TTL_MINUTES)->login($admin);

        JWTAuth::factory()->setTTL(self::REFRESH_TTL_MINUTES);
        $refreshToken = JWTAuth::customClaims([
            'type' => 'refresh',
            'guard' => 'vendor',
        ])->setTTL(self::REFRESH_TTL_MINUTES)->fromUser($admin);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
        ];
    }
}
