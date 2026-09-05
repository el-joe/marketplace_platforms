<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketerApiActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $marketerAdmin = auth()->guard('marketer_api')->user();

        if (! $marketerAdmin || ! $marketerAdmin->is_active) {
            return response()->json(['success' => false, 'message' => 'Account is inactive.'], 403);
        }

        $marketer = $marketerAdmin->marketer;

        if (in_array((string) $marketer->global_status, ['suspended', 'blacklisted', 'rejected'], true)) {
            return response()->json(['success' => false, 'message' => 'Account is suspended.'], 403);
        }

        return $next($request);
    }
}
