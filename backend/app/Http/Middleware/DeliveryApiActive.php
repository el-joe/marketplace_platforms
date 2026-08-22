<?php

namespace App\Http\Middleware;

use App\Enums\DeliveryAgentStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryApiActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\DeliveryAgent $agent */
        $agent = auth()->guard('delivery_api')->user();

        if (in_array($agent->status, [DeliveryAgentStatus::Suspended, DeliveryAgentStatus::Inactive], true)) {
            auth()->guard('delivery_api')->logout();

            $message = $agent->status === DeliveryAgentStatus::Suspended
                ? 'Your account has been suspended. Please contact support.'
                : 'Your account is not active.';

            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return $next($request);
    }
}
