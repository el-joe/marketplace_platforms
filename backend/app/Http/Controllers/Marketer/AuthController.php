<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 900; // 15 minutes

    // ── Registration ──────────────────────────────────────────────────────

    public function showRegister(): View
    {
        $countries = \App\Models\Country::where('is_active', true)->orderBy('name_ar')->get();
        return view('marketer.auth.register', compact('countries'));
    }

    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:marketer_admins,email'],
            'password'      => ['required', 'confirmed', PasswordRule::min(8)],
            'marketer_type' => ['required', 'in:influencer,affiliate'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'whatsapp_for_campaigns' => ['nullable', 'string', 'max:30'],
            'country_id'    => ['required', 'exists:countries,id'],
        ], [
            'email.unique'         => 'البريد الإلكتروني مسجّل مسبقاً.',
            'marketer_type.in'     => 'نوع الماركتر غير صحيح.',
            'country_id.exists'    => 'الدولة غير صحيحة.',
        ]);

        DB::transaction(function () use ($request) {
            $marketer = Marketer::create([
                'name'                   => $request->name,
                'email'                  => $request->email,
                'phone'                  => $request->phone,
                'marketer_type'          => $request->marketer_type,
                'whatsapp_for_campaigns' => $request->whatsapp_for_campaigns,
                'country_id'             => $request->country_id,
                'global_status'          => 'pending',
            ]);

            MarketerAdmin::create([
                'marketer_id' => $marketer->id,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => $request->password, // cast hashes automatically
                'is_owner'    => true,
                'is_active'   => true,
            ]);
        });

        return redirect()->route('marketer.login')
            ->with('status', 'تم تسجيل حسابك بنجاح! سيتم مراجعة طلبك والتواصل معك قريباً.');
    }

    // ── Login ─────────────────────────────────────────────────────────────

    public function showLogin(): View
    {
        return view('marketer.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
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
            'email'     => $request->input('email'),
            'password'  => $request->input('password'),
            'is_active' => 1,
        ];

        if (Auth::guard('marketer')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            /** @var \App\Models\MarketerAdmin $marketerAdmin */
            $marketerAdmin = Auth::guard('marketer')->user();
            $marketerAdmin->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return redirect()->intended(route('marketer.dashboard'));
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('marketer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketer.login');
    }

    // ── Forgot / Reset password ───────────────────────────────────────────

    public function forgotPassword(): View
    {
        return view('marketer.auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('marketer_admins')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.');
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }

    public function resetPassword(Request $request): View
    {
        return view('marketer.auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->query('email', ''),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('marketer_admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('marketer.login')
                ->with('status', 'تم إعادة تعيين كلمة المرور بنجاح.');
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }
}
