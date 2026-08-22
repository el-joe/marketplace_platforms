<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('delivery')->check()) {
            return redirect()->route('delivery.dashboard');
        }

        return view('delivery.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);


        if (Auth::guard('delivery')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $agent = Auth::guard('delivery')->user();
            $agent->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return redirect()->intended(route('delivery.dashboard'));
        }

        return back()
            ->withInput($request->only('phone'))
            ->withErrors(['phone' => __('delivery.messages.auth.invalid_credentials_web')]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('delivery')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('delivery.login');
    }
}
