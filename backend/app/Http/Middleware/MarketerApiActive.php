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

        if (in_array($marketer->global_status?->value, ['suspended', 'blacklisted', 'rejected'], true)) {
            return response()->json(['success' => false, 'message' => 'Account is suspended.'], 403);
        }

        if ($marketer->needsOnboarding()
            && ! str_ends_with($request->path(), '/me')
            && ! str_ends_with($request->path(), '/logout')
            && ! str_ends_with($request->path(), '/refresh')) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding required.',
                'code' => 'onboarding_required',
            ], 403);
        }

        return $next($request);
    }
}
