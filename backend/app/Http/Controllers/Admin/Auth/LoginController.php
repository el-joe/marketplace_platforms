<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminLoginSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // ── Rate limiting: 5 attempts per 15 min per IP ────────────────────────
        $key = 'admin-login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => __('admin.auth.too_many_attempts', ['seconds' => $seconds]),
                ]);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 900); // 15 minutes
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('admin.auth.invalid_credentials')]);
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();


        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        // Update last login details
        $admin->updateQuietly([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create login session record
        AdminLoginSession::create([
            'admin_id' => $admin->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Mark the login session as ended
        AdminLoginSession::where('admin_id', Auth::guard('admin')->id())
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()
                ?->update(['ended_at' => now()]);

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
