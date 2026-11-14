<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VendorActive
{
    /**
     * Allow only vendors whose global_status is active or under_review. Any other vendor is sent
     * to the account-status page (403 for JSON), which explains why: an application still awaiting
     * review, a rejected application, or a suspended account.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendorAdmin = Auth::guard('vendor')->user();

        if ($vendorAdmin) {
            $vendor = $vendorAdmin->vendor;

            if ($vendor && ! in_array($vendor->global_status->value, ['active', 'under_review'], true)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => __('partner.suspended.'.self::accountState($vendor).'_api_message'),
                    ], 403);
                }

                return redirect()->route('partner.suspended');
            }
        }

        return $next($request);
    }

    /**
     * Which account-status message a blocked vendor sees: pending, rejected or suspended
     * (inactive and blacklisted accounts read as suspended).
     */
    public static function accountState(?Vendor $vendor): string
    {
        return match ($vendor?->global_status?->value) {
            'pending' => 'pending',
            'rejected' => 'rejected',
            default => 'suspended',
        };
    }
}
