<?php

namespace App\Http\Middleware;

use App\Support\GuestRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('admin')->check()) {
            if ($request->expectsJson() || GuestRedirect::isApiRequest($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Please sign in to access the admin panel.');
        }

        return $next($request);
    }
}
