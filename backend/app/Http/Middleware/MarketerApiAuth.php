<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketerApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->guard('marketer_api')->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
