<?php

namespace App\Http\Middleware;

use App\Enums\VendorType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates partner routes to a single vendor_type. Usage: ->middleware('vendor.type:product_vendor')
 * or ->middleware('vendor.type:classified_vendor').
 */
class VendorTypeMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredType): Response
    {
        $vendorAdmin = auth()->guard('vendor')->user();

        if (! $vendorAdmin) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('partner.login');
        }

        $vendor = $vendorAdmin->vendor;
        $requiredEnum = VendorType::from($requiredType);

        if (! $vendor || $vendor->vendor_type !== $requiredEnum) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This section is not available for your account type.',
                ], 403);
            }

            abort(403, 'This section is not available for your account type.');
        }

        return $next($request);
    }
}
