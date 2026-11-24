<?php

namespace App\Http\Middleware;

use App\Support\GuestRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DeliveryAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('delivery')->check()) {
            if ($request->expectsJson() || GuestRedirect::isApiRequest($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('delivery.login');
        }

        return $next($request);
    }
}
