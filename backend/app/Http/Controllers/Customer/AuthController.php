<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CustomerStatus;
use App\Enums\DeviceTokenPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\ForgotPasswordRequest;
use App\Http\Requests\Customer\Auth\LoginRequest;
use App\Http\Requests\Customer\Auth\RefreshTokenRequest;
use App\Http\Requests\Customer\Auth\RegisterRequest;
use App\Http\Requests\Customer\Auth\ResetPasswordRequest;
use App\Http\Resources\Customer\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Notifications\Admin\NewCustomerRegistered;
use App\Services\Customer\CustomerPasswordResetService;
use App\Services\Customer\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private const ACCESS_TTL_MINUTES = 60;

    private const REFRESH_TTL_MINUTES = 43200; // 30 days

    public function __construct(
        private readonly ReferralService $referralService,
        private readonly CustomerPasswordResetService $passwordResetService,
    ) {}

    // ── Register ──────────────────────────────────────────────────────────────

    public function register(RegisterRequest $request, $country): JsonResponse
    {
        /** @var Country $country */
        $country = $request->attributes->get('country');

        $customer = DB::transaction(function () use ($request, $country): Customer {
            $customer = Customer::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password,
                'gender' => $request->validated('gender'),
                'country_id' => $country->id,
                'referral_code' => $this->referralService->generateUniqueCode(),
                'status' => CustomerStatus::Active,
            ]);

            if ($request->filled('referral_code')) {
                $this->referralService->applyReferral($customer, $request->referral_code);
            }

            return $customer;
        });

        Notification::send(Admin::permission('customers.view')->get(), new NewCustomerRegistered($customer));

        $tokens = $this->issueTokenPair($customer);

        return ApiResponse::success(
            array_merge(['customer' => new CustomerResource($customer)], $tokens),
            __('common.exceptions.auth.registration_successful'),
            201
        );
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(LoginRequest $request, $country): JsonResponse
    {
        $credential = $request->email_or_phone;
        $field = filter_var($credential, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        /** @var Customer|null $customer */
        $customer = Customer::where($field, $credential)->first();

        if (! $customer || ! password_verify($request->password, $customer->password)) {
            return ApiResponse::error(__('common.exceptions.auth.invalid_credentials'), [], 401);
        }

        if (in_array($customer->status, [CustomerStatus::Suspended, CustomerStatus::Banned, CustomerStatus::Deleted], true)) {
            $reason = match ($customer->status) {
                CustomerStatus::Suspended => __('common.exceptions.auth.account_suspended'),
                CustomerStatus::Banned => __('common.exceptions.auth.account_banned'),
                CustomerStatus::Deleted => __('common.exceptions.auth.account_no_longer_exists'),
            };

            return ApiResponse::error($reason, [], 403);
        }

        $customer->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $tokens = $this->issueTokenPair($customer);

        return ApiResponse::success(
            array_merge(['customer' => new CustomerResource($customer)], $tokens),
            __('common.exceptions.auth.login_successful')
        );
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout($country): JsonResponse
    {
        $customer = auth('customer')->user();
        $token = JWTAuth::getToken()?->get();

        auth('customer')->logout();

        if ($customer && $token) {
            DeviceToken::where('tokenable_type', Customer::class)
                ->where('tokenable_id', $customer->id)
                ->where('token', hash('sha256', $token))
                ->update(['is_active' => false]);
        }

        return ApiResponse::success(null, __('common.exceptions.auth.logged_out'));
    }

    // ── Refresh Token ─────────────────────────────────────────────────────────

    public function refreshToken(RefreshTokenRequest $request, $country): JsonResponse
    {
        try {
            $payload = JWTAuth::setToken($request->refresh_token)->getPayload();
        } catch (\Throwable) {
            return ApiResponse::error(__('common.exceptions.auth.invalid_refresh_token'), [], 401);
        }

        if (($payload->get('type') ?? '') !== 'refresh') {
            return ApiResponse::error(__('common.exceptions.auth.invalid_token_type'), [], 401);
        }

        if (($payload->get('guard') ?? '') !== 'customer') {
            return ApiResponse::error(__('common.exceptions.auth.invalid_token_guard'), [], 401);
        }

        $customer = Customer::find($payload->getSubject());

        if (! $customer || $customer->status !== CustomerStatus::Active) {
            return ApiResponse::error(__('common.exceptions.auth.account_not_found'), [], 401);
        }

        // Invalidate the used refresh token
        JWTAuth::setToken($request->refresh_token)->invalidate();

        $tokens = $this->issueTokenPair($customer);

        return ApiResponse::success($tokens, __('common.exceptions.auth.token_refreshed'));
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function me($country): JsonResponse
    {
        return ApiResponse::success(
            new CustomerResource(auth('customer')->user()),
            __('common.exceptions.auth.profile_retrieved')
        );
    }

    // ── Forgot Password ───────────────────────────────────────────────────────

    public function forgotPassword(ForgotPasswordRequest $request, $country): JsonResponse
    {
        /** @var Country $country */
        $country = $request->attributes->get('country');

        $this->passwordResetService->sendResetLink(
            (string) $request->input('email_or_phone'),
            $country,
            $request->resolvedLocale(),
        );

        // Same response whether or not the account exists (no user enumeration).
        return ApiResponse::success(null, __('common.exceptions.auth.reset_link_sent'));
    }

    // ── Reset Password ────────────────────────────────────────────────────────

    public function resetPassword(ResetPasswordRequest $request, $country): JsonResponse
    {
        $status = $this->passwordResetService->reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(__('common.exceptions.auth.invalid_reset_token'), [], 422);
        }

        return ApiResponse::success(null, __('common.exceptions.auth.password_reset'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function issueTokenPair(Customer $customer): array
    {
        $accessToken = auth('customer')->setTTL(self::ACCESS_TTL_MINUTES)->login($customer);

        JWTAuth::factory()->setTTL(self::REFRESH_TTL_MINUTES);

        $refreshToken = JWTAuth::customClaims([
            'type' => 'refresh',
            'guard' => 'customer',
        ])->fromUser($customer);

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => Customer::class,
                'tokenable_id' => $customer->id,
                'token' => hash('sha256', $accessToken),
            ],
            [
                'platform' => DeviceTokenPlatform::Web,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
        ];
    }
}
