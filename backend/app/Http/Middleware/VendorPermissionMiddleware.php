<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $vendorAdmin = auth()->guard('vendor')->user();

        if (! $vendorAdmin) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('partner.login');
        }

        if (! $vendorAdmin->can($permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "You don't have permission to perform this action.",
                ], 403);
            }

            return redirect()->back()->with('error', "You don't have permission to perform this action.");
        }

        return $next($request);
    }
}
