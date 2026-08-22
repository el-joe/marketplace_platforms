<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('portal.register');
    }

    public function register(Request $request): RedirectResponse
    {
        // Full registration logic will be implemented in PORTAL-02.
        return redirect()->route('portal.home')
            ->with('success', __('portal.auth.registration_thank_you'));
    }
}
