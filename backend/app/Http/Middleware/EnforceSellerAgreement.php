<?php

namespace App\Http\Middleware;

use App\Services\Vendor\SellerAgreementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSellerAgreement
{
    public function __construct(private readonly SellerAgreementService $agreements) {}

    /**
     * Click-wrap gate: while the vendor has not accepted the Seller Agreement version that applies to them,
     * every panel page sends them to the acceptance screen. Notice-period versions never block.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendorAdmin = auth('vendor')->user();

        if (! $vendorAdmin || ! $vendorAdmin->vendor || $request->routeIs('partner.legal.*', 'partner.logout', 'partner.suspended', 'partner.locale.*')) {
            return $next($request);
        }

        if ($this->agreements->pendingFor($vendorAdmin->vendor) === null) {
            return $next($request);
        }

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            return response()->json([
                'success' => false,
                'code' => 'agreement_required',
                'message' => __('partner.legal.gate_blocked'),
                'redirect' => route('partner.legal.agreement'),
            ], 423);
        }

        session(['url.intended' => $request->fullUrl()]);

        return redirect()->route('partner.legal.agreement');
    }
}
