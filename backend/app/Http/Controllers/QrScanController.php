<?php

namespace App\Http\Controllers;

use App\Models\MarketerProfile;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;

/**
 * Handles QR code scan redirects. These routes live on the public web (no auth).
 * A scanned QR points here, and we redirect to the correct frontend storefront page.
 */
class QrScanController extends Controller
{
    public function product(string $slug): RedirectResponse
    {
        Product::where('slug', $slug)->firstOrFail();

        return redirect($this->frontendUrl("/products/{$slug}"));
    }

    public function vendor(string $slug): RedirectResponse
    {
        $vendor = Vendor::where('store_slug', $slug)->firstOrFail();

        return redirect($this->frontendUrl("/seller/{$vendor->id}"));
    }

    public function marketer(string $slug): RedirectResponse
    {
        MarketerProfile::where('profile_slug', $slug)->firstOrFail();

        return redirect($this->frontendUrl("/marketer/{$slug}"));
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', '/')), '/');
        $locale = config('app.frontend_default_locale', 'uae-en');

        return "{$base}/{$locale}{$path}";
    }
}
