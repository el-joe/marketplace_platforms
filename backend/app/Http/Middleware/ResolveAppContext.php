<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAppContext
{
    public const VALID_CONTEXTS = ['main', 'nawy_now', 'classifieds', 'travel'];

    public function handle(Request $request, Closure $next): Response
    {
        $contextKey = $request->header('X-App-Context', 'main');

        if (!in_array($contextKey, self::VALID_CONTEXTS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app context',
                'data' => null,
            ], 400);
        }

        app()->instance('app.context', $contextKey);
        $request->merge(['_app_context' => $contextKey]);

        return $next($request);
    }
}
