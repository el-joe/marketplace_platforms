<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\TravelAgencyMember;

class AuthController extends Controller
{
    use ResolvesTravelAgency;

    private const MAX_ATTEMPTS   = 5;
    private const DECAY_SECONDS  = 900;

    // ── Login ─────────────────────────────────────────────────────────────────

    public function showLogin(): View
    {
        return view('travel-agency.auth.login');
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

        if (Auth::guard('travel_agency')->attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        )) {
            RateLimiter::clear($throttleKey);

            $user = Auth::guard('travel_agency')->user();
            if ($user instanceof TravelAgencyMember) {
                $user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ]);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('travel-agency.dashboard'));
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'email' => __('travel.auth.invalid_credentials'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('travel_agency')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('travel-agency.login');
    }
}
