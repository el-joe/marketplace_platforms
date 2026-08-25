<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorApiActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendorAdmin = auth()->guard('vendor_api')->user();
        $vendor      = $vendorAdmin?->vendor;

        if ($vendor && !in_array($vendor->global_status->value, ['active', 'under_review'], true)) {
            auth()->guard('vendor_api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your store is currently ' . $vendor->global_status->value . '.',
                'status'  => $vendor->global_status->value,
            ], 403);
        }

        return $next($request);
    }
}
