<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerProfile;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\BrandedQrService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class QrCodeController extends Controller
{
    public function __construct(private BrandedQrService $qrService) {}

    /**
     * Download a branded QR code for a product.
     * Label shows the product name (or SKU if preferred).
     */
    public function product(string $slug): Response
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $url = route('qr.scan.product', ['slug' => $product->slug]);
        $label = $product->name ?? $product->slug;
        $png = $this->qrService->generate($url, $label);

        return $this->pngDownloadResponse($png, 'product-'.$product->slug.'-qr.png');
    }

    /**
     * Download a branded QR code for a vendor/seller store.
     */
    public function vendor(string $slug): Response
    {
        $vendor = Vendor::where('store_slug', $slug)->firstOrFail();

        $url = route('qr.scan.vendor', ['slug' => $vendor->store_slug]);
        $label = $vendor->store_name ?? $vendor->name;
        $png = $this->qrService->generate($url, $label);

        return $this->pngDownloadResponse($png, 'vendor-'.$vendor->store_slug.'-qr.png');
    }

    /**
     * Download a branded QR code for a marketer/celebrity profile.
     */
    public function marketer(string $profileSlug): Response
    {
        $profile = MarketerProfile::where('profile_slug', $profileSlug)->firstOrFail();
        $name = $profile->marketer?->name ?? $profileSlug;

        $url = route('qr.scan.marketer', ['slug' => $profileSlug]);
        $label = $name;
        $png = $this->qrService->generate($url, $label);

        return $this->pngDownloadResponse($png, 'marketer-'.$profileSlug.'-qr.png');
    }

    /**
     * Vendor downloads QR for their own store (auth-derived, no slug param needed).
     */
    public function myVendorStore(): Response
    {
        /** @var Vendor $vendor */
        $vendor = Auth::guard('vendor')->user();

        $url = route('qr.scan.vendor', ['slug' => $vendor->store_slug]);
        $label = $vendor->store_name ?? $vendor->name;
        $png = $this->qrService->generate($url, $label);

        return $this->pngDownloadResponse($png, 'store-'.$vendor->store_slug.'-qr.png');
    }

    /**
     * Marketer downloads their own profile QR (auth-derived).
     */
    public function myMarketerProfile(): Response
    {
        /** @var Marketer $marketer */
        $marketer = Auth::guard('marketer')->user();
        $profile = $marketer->marketerProfile()->firstOrFail();

        $url = route('qr.scan.marketer', ['slug' => $profile->profile_slug]);
        $label = $marketer->name;
        $png = $this->qrService->generate($url, $label);

        return $this->pngDownloadResponse($png, 'marketer-'.$profile->profile_slug.'-qr.png');
    }

    private function pngDownloadResponse(string $png, string $filename): Response
    {
        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
