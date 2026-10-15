<?php

namespace App\Http\Middleware;

use App\Enums\VendorType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates partner routes to one or more vendor_types. Usage: ->middleware('vendor.type:product_vendor'),
 * ->middleware('vendor.type:classified_vendor') or, for sections both kinds of vendor use,
 * ->middleware('vendor.type:product_vendor,classified_vendor').
 */
class VendorTypeMiddleware
{
    public function handle(Request $request, Closure $next, string ...$requiredTypes): Response
    {
        $vendorAdmin = auth()->guard('vendor')->user();

        if (! $vendorAdmin) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('partner.login');
        }

        $vendor = $vendorAdmin->vendor;
        $allowed = array_map(fn (string $type) => VendorType::from($type), $requiredTypes);

        if (! $vendor || ! in_array($vendor->vendor_type, $allowed, true)) {
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
