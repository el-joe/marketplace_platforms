<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CarrierPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var \App\Models\ShippingCompanySupervisor $supervisor */
        $supervisor = auth()->guard('shipping_supervisor_api')->user();

        if (! $supervisor->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => "You do not have the required permission: {$permission}.",
            ], 403);
        }

        return $next($request);
    }
}
