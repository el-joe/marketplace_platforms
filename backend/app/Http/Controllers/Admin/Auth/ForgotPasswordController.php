<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Staff "forgot password": emails a single-use link to the existing set-password page
 * (admin.password.reset, broker `admins`). The answer never reveals whether the address exists.
 */
class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $isActiveAdmin = Admin::query()
            ->where('email', $request->string('email')->toString())
            ->where('status', AdminStatus::Active)
            ->exists();

        if ($isActiveAdmin) {
            // RESET_THROTTLED / mail failures are deliberately not surfaced (logged by CredentialDeliveryService).
            Password::broker('admins')->sendResetLink($request->only('email'));
        }

        return back()->with('status', __('admin.auth.reset_link_sent'));
    }
}
