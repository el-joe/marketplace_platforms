<?php

namespace App\Http\Middleware;

use App\Models\VendorApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorDualAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth()->guard('vendor_api');

        // Attempt JWT first
        if ($guard->check()) {
            $request->attributes->set('api_auth_method', 'jwt');

            return $next($request);
        }

        // Fall back to static bearer token
        $bearerToken = $request->bearerToken();

        if ($bearerToken && str_starts_with($bearerToken, 'vnd_')) {
            $hashed = hash('sha256', $bearerToken);

            $apiToken = VendorApiToken::where('token', $hashed)
                ->with('vendorAdmin')
                ->first();

            if ($apiToken && $apiToken->vendorAdmin) {
                $apiToken->update(['last_used_at' => now()]);
                $guard->setUser($apiToken->vendorAdmin);

                $request->attributes->set('api_auth_method', 'token');
                $request->attributes->set('api_token_id', $apiToken->id);

                return $next($request);
            }
        }

        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }
}
