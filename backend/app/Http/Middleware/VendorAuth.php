<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VendorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('vendor')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('partner.login');
        }

        return $next($request);
    }
}
