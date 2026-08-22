<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TravelAgencyPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $member = auth()->guard('travel_agency')->user();

        if (!$member) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('travel-agency.login');
        }

        if ($member->is_owner) {
            return $next($request);
        }

        if (!$member->can($permission)) {
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
