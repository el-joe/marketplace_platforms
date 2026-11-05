<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Read by Illuminate\Http\Middleware\TrustProxies. Only the listed proxy
| addresses may supply X-Forwarded-For / -Proto / -Host, so a visitor cannot
| spoof their IP (which drives country detection and rate limits).
|
| Production nginx talks to PHP-FPM directly (deploy/server-setup.sh), so
| REMOTE_ADDR is the real client and nothing needs trusting beyond loopback.
| If a CDN / load balancer (e.g. Cloudflare) fronts nginx, set TRUSTED_PROXIES
| to a comma-separated list of that proxy's IPs / CIDR ranges. Use '*' only
| when the web server is reachable exclusively through such a proxy.
|
*/

return [
    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),
];
