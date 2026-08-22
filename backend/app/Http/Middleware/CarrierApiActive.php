<?php

namespace App\Http\Middleware;

use App\Enums\ShippingCompanyStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CarrierApiActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\ShippingCompanySupervisor $supervisor */
        $supervisor = auth()->guard('shipping_supervisor_api')->user();

        if (! $supervisor->is_active) {
            auth()->guard('shipping_supervisor_api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Contact your company administrator.',
            ], 403);
        }

        $company = $supervisor->company;

        if ($company->status === ShippingCompanyStatus::Pending) {
            auth()->guard('shipping_supervisor_api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your company is pending platform approval.',
                'company_status' => ShippingCompanyStatus::Pending->value,
            ], 403);
        }

        if ($company->status === ShippingCompanyStatus::Suspended) {
            auth()->guard('shipping_supervisor_api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your company has been suspended. Please contact platform support.',
                'company_status' => ShippingCompanyStatus::Suspended->value,
            ], 403);
        }

        return $next($request);
    }
}
