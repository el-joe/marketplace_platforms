<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->guard('marketer')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('marketer.login');
        }

        $marketerAdmin = auth()->guard('marketer')->user();

        if (! $marketerAdmin->is_active) {
            auth()->guard('marketer')->logout();

            return redirect()->route('marketer.login')
                ->withErrors(['email' => 'حسابك غير مفعّل. تواصل مع الدعم.']);
        }

        $marketer = $marketerAdmin->marketer;

        if (in_array((string) $marketer->global_status, ['suspended', 'blacklisted'], true)) {
            auth()->guard('marketer')->logout();

            return redirect()->route('marketer.login')
                ->withErrors(['email' => 'تم تعليق حسابك. تواصل مع الدعم.']);
        }

        if ((string) $marketer->global_status === 'pending') {
            // Allow access to a pending page only, or show a banner — do not hard-logout
            $request->attributes->set('marketer_pending', true);
        }

        return $next($request);
    }
}
