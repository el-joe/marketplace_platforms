<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubdomainDetect
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.domain', env('APP_DOMAIN', 'localhost'));

        // Extract subdomain: "portal" from "portal.noon.loc"
        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = substr($host, 0, strlen($host) - strlen('.' . $baseDomain));
        } else {
            $subdomain = null;
        }

        config(['app.subdomain' => $subdomain]);

        return $next($request);
    }
}
