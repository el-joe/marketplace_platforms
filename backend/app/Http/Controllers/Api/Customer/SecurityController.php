<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\DeviceSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerOtpToken;
use App\Models\DeviceToken;
use App\Notifications\Customer\SendOtpNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SecurityController extends Controller
{
    private const OTP_RESEND_SECONDS = 60;
    private const OTP_TTL_MINUTES = 15;

    // ── Change password ──────────────────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        if (!Hash::check($request->input('current_password'), $customer->password)) {
            return ApiResponse::error(__('customer_api.security.current_password_incorrect'), [], 422);
        }

        $customer->update(['password' => $request->input('new_password')]);

        // Invalidate every JWT issued to this customer, including the one used for this request.
        auth('customer')->logout(true);

        return ApiResponse::success(null, __('customer_api.security.password_changed'));
    }

    // ── Email verification ───────────────────────────────────────────────────

    public function sendEmailVerificationOtp(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        if ($customer->email_verified_at !== null) {
            return ApiResponse::error(__('customer_api.security.email_already_verified'), [], 422);
        }

        if ($error = $this->rateLimitOtp($customer, 'email_verification')) {
            return $error;
        }

        $customer->otpTokens()
            ->where('type', 'email_verification')
            ->whereNull('used_at')
            ->delete();

        $otp = $this->generateNumericOtp();

        CustomerOtpToken::create([
            'customer_id' => $customer->id,
            'token' => Hash::make($otp),
            'type' => 'email_verification',
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        $customer->notify(new SendOtpNotification($customer->id, $otp, 'email verification'));

        return ApiResponse::success(
            null,
            __('customer_api.security.otp_sent_to', ['destination' => $this->maskEmail($customer->email)])
        );
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $otp = $this->matchOtp($customer, 'email_verification', $request->input('otp'));

        if (!$otp) {
            return ApiResponse::error(__('customer_api.security.invalid_or_expired_otp'), [], 422);
        }

        DB::transaction(function () use ($otp, $customer): void {
            $otp->update(['used_at' => now()]);
            $customer->update(['email_verified_at' => now()]);
        });

        return ApiResponse::success(null, __('customer_api.security.email_verified'));
    }

    // ── Phone verification ───────────────────────────────────────────────────

    public function sendPhoneVerificationOtp(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        if ($customer->phone_verified_at !== null) {
            return ApiResponse::error(__('customer_api.security.phone_already_verified'), [], 422);
        }

        if ($error = $this->rateLimitOtp($customer, 'phone_verification')) {
            return $error;
        }

        $customer->otpTokens()
            ->where('type', 'phone_verification')
            ->whereNull('used_at')
            ->delete();

        $otp = $this->generateNumericOtp();

        CustomerOtpToken::create([
            'customer_id' => $customer->id,
            'token' => Hash::make($otp),
            'type' => 'phone_verification',
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        $customer->notify(new SendOtpNotification($customer->id, $otp, 'phone verification'));

        return ApiResponse::success(
            null,
            __('customer_api.security.otp_sent_to', ['destination' => $this->maskPhone($customer->phone)])
        );
    }

    public function verifyPhoneOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $otp = $this->matchOtp($customer, 'phone_verification', $request->input('otp'));

        if (!$otp) {
            return ApiResponse::error(__('customer_api.security.invalid_or_expired_otp'), [], 422);
        }

        DB::transaction(function () use ($otp, $customer): void {
            $otp->update(['used_at' => now()]);
            $customer->update(['phone_verified_at' => now()]);
        });

        return ApiResponse::success(null, __('customer_api.security.phone_verified'));
    }

    // ── Sessions / devices ───────────────────────────────────────────────────

    public function activeSessions(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $devices = DeviceSessionResource::collection(
            DeviceToken::query()
                ->where('tokenable_type', $customer::class)
                ->where('tokenable_id', $customer->getKey())
                ->where('is_active', true)
                ->orderByDesc('last_used_at')
                ->get()
        );

        $sessionCount = DB::table('sessions')
            ->where('user_id', $customer->getKey())
            ->count();

        return ApiResponse::success([
            'devices' => $devices,
            'session_count' => $sessionCount,
        ], __('customer_api.security.sessions_retrieved'));
    }

    public function revokeDevice(Request $request, string $device_token_id): JsonResponse
    {
        $validator = Validator::make(['token_id' => $device_token_id], [
            'token_id' => ['required', 'uuid'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $device = DeviceToken::query()
            ->where('id', $device_token_id)
            ->where('tokenable_type', $customer::class)
            ->where('tokenable_id', $customer->getKey())
            ->first();

        if (!$device) {
            return ApiResponse::error(__('customer_api.security.device_not_found'), [], 404);
        }

        $device->update(['is_active' => false]);

        return ApiResponse::success(null, __('customer_api.security.device_revoked'));
    }

    public function revokeAllDevices(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        DeviceToken::query()
            ->where('tokenable_type', $customer::class)
            ->where('tokenable_id', $customer->getKey())
            ->where('is_active', true)
            ->when($request->filled('current_device_token'), function ($query) use ($request) {
                $query->where('token', '!=', $request->input('current_device_token'));
            })
            ->update(['is_active' => false]);

        auth('customer')->logout(true);

        return ApiResponse::success(null, __('customer_api.security.all_devices_revoked'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function rateLimitOtp(Customer $customer, string $type): ?JsonResponse
    {
        $lastToken = $customer->otpTokens()
            ->where('type', $type)
            ->orderByDesc('created_at')
            ->first();

        if ($lastToken && $lastToken->created_at->diffInSeconds(now()) < self::OTP_RESEND_SECONDS) {
            $retryAfter = self::OTP_RESEND_SECONDS - $lastToken->created_at->diffInSeconds(now());

            return ApiResponse::error(
                __('customer_api.security.otp_wait', ['seconds' => $retryAfter]),
                [],
                429
            );
        }

        return null;
    }

    private function matchOtp(Customer $customer, string $type, string $otp): ?CustomerOtpToken
    {
        $candidates = $customer->otpTokens()
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($otp, $candidate->token)) {
                return $candidate;
            }
        }

        return null;
    }

    private function generateNumericOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        $visible = min(2, strlen($local));

        return substr($local, 0, $visible) . str_repeat('*', max(1, strlen($local) - $visible)) . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($phone, -4);
    }
}
