<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestCartToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Accept cart token from multiple sources (priority order):
        // 1. X-Cart-Token header (recommended)
        // 2. cart_token query parameter
        // 3. cart_token in request body
        $token = $request->header('X-Cart-Token')
              ?? $request->query('cart_token')
              ?? $request->input('cart_token');

        if ($token) {
            $request->attributes->set('guest_cart_token', $token);
        }

        return $next($request);
    }
}
