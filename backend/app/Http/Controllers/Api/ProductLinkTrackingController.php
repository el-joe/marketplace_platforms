<?php

namespace App\Http\Controllers\Api;

use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorListing;
use App\Services\ProductLink\ProductLinkAttributionService;
use App\Services\ProductLink\ProductShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /api/s/{token} — WS12 #24. Opened by the storefront page /s/{token} (the vendor's product link or the QR code
 * printed on the product). A valid token records the click for the visitor's session and redirects to the product page with the vendor's offer selected;
 * an invalid one records nothing and lands on the home page.
 */
class ProductLinkTrackingController extends Controller
{
    public function __construct(
        private readonly ProductShareLinkService $links,
        private readonly ProductLinkAttributionService $attribution,
    ) {}

    public function track(string $token, Request $request): RedirectResponse|JsonResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $listing = $this->links->resolve(strtolower($token));

        if (! $listing) {
            return $this->respond($request, $frontendUrl, false);
        }

        $sessionId = $request->header('X-Session-Id')
            ?? $request->query('session_id')
            ?? $request->cookie('session_id');

        if (is_string($sessionId) && $sessionId !== '') {
            $this->attribution->recordClick($listing, $sessionId, (string) $request->query('c', ProductShareLinkService::CHANNEL_LINK));
        }

        return $this->respond($request, $this->destinationFor($listing, $frontendUrl), true);
    }

    /**
     * The product page with THIS vendor's offer selected (the storefront resolves `/products/{listing id}` to that
     * listing), so the buyer adds the offer that earns the discount. A listing no longer on sale falls back to the
     * product page (best offer), then the home page.
     */
    private function destinationFor(VendorListing $listing, string $frontendUrl): string
    {
        if ($listing->status === VendorListingStatus::Active) {
            return $frontendUrl.'/products/'.$listing->id;
        }

        $slug = $listing->productVariant?->product?->slug;

        return $slug ? $frontendUrl.'/products/'.$slug : $frontendUrl;
    }

    private function respond(Request $request, string $destination, bool $attributed): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['destination' => $destination, 'attributed' => $attributed])
            : redirect($destination);
    }
}
