<?php

namespace App\Http\Middleware;

use App\Services\Vendor\CategoryContractService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceVendorCategoryContracts
{
    public function __construct(private readonly CategoryContractService $contractService) {}

    /**
     * Redirect the vendor to the pending-contracts page while any contract for their
     * active categories is unsigned or outdated. The original URL is kept so the
     * vendor returns to it once everything is accepted.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendorAdmin = auth('vendor')->user();

        if (! $vendorAdmin || ! $vendorAdmin->vendor) {
            return $next($request);
        }

        if ($request->routeIs('partner.contracts.*')) {
            return $next($request);
        }

        $pending = $this->contractService->pendingForVendor($vendorAdmin->vendor);

        if ($pending->isEmpty()) {
            return $next($request);
        }

        session(['url.intended' => $request->fullUrl()]);

        return redirect()->route('partner.contracts.pending');
    }
}
