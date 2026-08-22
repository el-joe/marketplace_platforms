<?php

namespace App\Http\Middleware;

use App\Enums\ShippingCompanyStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShippingCompanySupervisorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->guard('shipping_supervisor')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('carrier.login');
        }

        $supervisor = auth()->guard('shipping_supervisor')->user();

        if (! $supervisor->is_active) {
            auth()->guard('shipping_supervisor')->logout();

            return redirect()->route('carrier.login')
                ->withErrors(['email' => 'حسابك موقوف. تواصل مع مدير الشركة.']);
        }

        if ($supervisor->company->status === ShippingCompanyStatus::Suspended) {
            auth()->guard('shipping_supervisor')->logout();

            return redirect()->route('carrier.login')
                ->withErrors(['email' => 'شركتك موقوفة من المنصة. تواصل مع الدعم.']);
        }

        if ($supervisor->company->status === ShippingCompanyStatus::Pending) {
            auth()->guard('shipping_supervisor')->logout();

            return redirect()->route('carrier.login')
                ->withErrors(['email' => 'شركتك قيد المراجعة من الإدارة.']);
        }

        return $next($request);
    }
}
