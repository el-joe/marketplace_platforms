<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerOtpToken;
use App\Notifications\Customer\SendOtpNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    private const OTP_TTL_MINUTES    = 15;
    private const OTP_RESEND_SECONDS = 60;

    // ── Password reset OTP ───────────────────────────────────────────────────

    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        $credential = $validator->validated()['email_or_phone'];
        $field      = filter_var($credential, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $customer = Customer::where($field, $credential)
            ->where('status', 'active')
            ->first();

        if (!$customer) {
            return ApiResponse::success(null, __('customer_api.security.otp_sent_to', ['destination' => '***']));
        }

        if ($error = $this->rateLimitOtp($customer, 'password_reset')) {
            return $error;
        }

        $customer->otpTokens()
            ->where('type', 'password_reset')
            ->whereNull('used_at')
            ->delete();

        $otp = $this->generateNumericOtp();

        CustomerOtpToken::create([
            'customer_id' => $customer->id,
            'token' => Hash::make($otp),
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        $customer->notify(new SendOtpNotification($customer->id, $otp, 'password reset'));

        $destination = $field === 'email'
            ? $this->maskEmail($customer->email)
            : $this->maskPhone($customer->phone ?? '');

        return ApiResponse::success(
            null,
            __('customer_api.security.otp_sent_to', ['destination' => $destination])
        );
    }

    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => ['required', 'string', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        $data       = $validator->validated();
        $credential = $data['email_or_phone'];
        $field      = filter_var($credential, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $customer = Customer::where($field, $credential)
            ->where('status', 'active')
            ->first();

        if (!$customer) {
            return ApiResponse::error(__('customer_api.security.invalid_or_expired_otp'), [], 422);
        }

        $otp = $this->matchOtp($customer, 'password_reset', $data['otp']);

        if (!$otp) {
            return ApiResponse::error(__('customer_api.security.invalid_or_expired_otp'), [], 422);
        }

        $otp->update(['used_at' => now()]);
        $customer->update(['password' => $data['new_password']]);

        return ApiResponse::success(null, __('common.exceptions.auth.password_reset'));
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
