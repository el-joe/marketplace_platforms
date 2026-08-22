<?php

namespace App\Http\Controllers\CarrierPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 900;

    public function showLogin(): View
    {
        return view('carrier.auth.login');
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

        if (Auth::guard('shipping_supervisor')->attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        )) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('carrier.dashboard'));
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'email' => __('carrier.errors.invalid_credentials'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('shipping_supervisor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('carrier.login');
    }
}
