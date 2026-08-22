<?php

namespace App\Http\Middleware;

use App\Enums\TravelAgencyStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TravelAgencyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->guard('travel_agency')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('travel-agency.login');
        }

        $user = auth()->guard('travel_agency')->user();

        $isActive = $user->is_active;
        $agency = $user->travelAgency;

        if (!$isActive) {
            auth()->guard('travel_agency')->logout();

            return redirect()->route('travel-agency.login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact your agency owner.']);
        }

        if ($agency->status === TravelAgencyStatus::Suspended) {
            auth()->guard('travel_agency')->logout();

            return redirect()->route('travel-agency.login')
                ->withErrors(['email' => 'Your account has been suspended. Please contact support.']);
        }

        if ($agency->status === TravelAgencyStatus::Pending) {
            auth()->guard('travel_agency')->logout();

            return redirect()->route('travel-agency.login')
                ->withErrors(['email' => 'Your account is pending admin approval.']);
        }

        return $next($request);
    }
}
