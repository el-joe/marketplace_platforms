<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class OptionalCustomerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($token = JWTAuth::getToken()) {
                JWTAuth::setToken($token)->authenticate();
            }
        } catch (JWTException) {
            // Invalid or expired token — treat as guest, do not reject
        }

        return $next($request);
    }
}
