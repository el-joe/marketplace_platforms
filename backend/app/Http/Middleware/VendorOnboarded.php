<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VendorOnboarded
{
    /**
     * Ensure the vendor has completed onboarding.
     * If not, redirect to the onboarding wizard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendorAdmin = Auth::guard('vendor')->user();

        if ($vendorAdmin) {
            $vendor = $vendorAdmin->vendor;

            if ($vendor && is_null($vendor->onboarding_completed_at)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please complete your onboarding first.',
                    ], 403);
                }

                return redirect()->route('partner.onboarding');
            }
        }

        return $next($request);
    }
}
