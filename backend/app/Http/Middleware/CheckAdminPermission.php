<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:  ->middleware('admin.permission:orders.view')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || !$admin->can($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
