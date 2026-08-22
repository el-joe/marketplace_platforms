<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900; // 15 minutes

    // ────────────────────────────────────────────────
    // Login
    // ────────────────────────────────────────────────

    public function showLogin(): View
    {
        return view('partner.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'is_active' => 1,
        ];

        if (Auth::guard('vendor')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            /** @var \App\Models\VendorAdmin $vendorAdmin */
            $vendorAdmin = Auth::guard('vendor')->user();
            $vendorAdmin->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return redirect()->intended(route('partner.dashboard'));
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('vendor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(config('app.portal_url', 'http://portal.noon.loc'));
    }

    // ────────────────────────────────────────────────
    // Forgot Password
    // ────────────────────────────────────────────────

    public function forgotPassword(): View
    {
        return view('partner.auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker('vendor_admins')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.');
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }

    // ────────────────────────────────────────────────
    // Reset Password
    // ────────────────────────────────────────────────

    public function resetPassword(Request $request): View
    {
        return view('partner.auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->query('email', ''),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('vendor_admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('partner.login')
                ->with('status', 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.');
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }
}
