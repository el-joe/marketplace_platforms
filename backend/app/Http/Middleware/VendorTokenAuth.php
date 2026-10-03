<?php

namespace App\Http\Middleware;

use App\Models\VendorApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken || ! str_starts_with($bearerToken, 'vnd_')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $hashed = hash('sha256', $bearerToken);

        $apiToken = VendorApiToken::where('token', $hashed)
            ->with('vendorAdmin.vendor')
            ->first();

        if (! $apiToken || ! $apiToken->vendorAdmin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $vendor = $apiToken->vendorAdmin?->vendor;

        if (! $vendor?->external_api_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'External API access is not enabled for this account. Contact support.',
            ], 403);
        }

        $apiToken->update(['last_used_at' => now()]);

        auth()->guard('vendor_api')->setUser($apiToken->vendorAdmin);

        $request->attributes->set('api_auth_method', 'token');
        $request->attributes->set('api_token_id', $apiToken->id);

        return $next($request);
    }
}
