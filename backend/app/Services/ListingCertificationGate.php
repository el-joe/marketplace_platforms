<?php

namespace App\Services;

use App\Models\ProductCountry;
use App\Models\VendorListing;
use App\Models\VendorProductCertification;
use Illuminate\Validation\ValidationException;

class ListingCertificationGate
{
    /**
     * Throws a validation error if the listing's product requires a local
     * certification in its country and the vendor has no approved one on file.
     */
    public static function assertCanGoLive(VendorListing $listing): void
    {
        $productId = $listing->productVariant?->product_id ?? $listing->loadMissing('productVariant')->productVariant->product_id;

        static::assertCanGoLiveFor($listing->vendor_id, $productId, $listing->country_id);
    }

    public static function assertCanGoLiveFor(string $vendorId, string $productId, string $countryId): void
    {
        $requiresCert = ProductCountry::where('product_id', $productId)
            ->where('country_id', $countryId)
            ->where('requires_local_cert', 1)
            ->exists();

        if (! $requiresCert) {
            return;
        }

        $hasApprovedCert = VendorProductCertification::where('vendor_id', $vendorId)
            ->where('product_id', $productId)
            ->where('country_id', $countryId)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $hasApprovedCert) {
            throw ValidationException::withMessages([
                'status' => ['A valid local certification is required for this product in this country before the listing can go live. Please upload your certification from the Product Certifications section.'],
            ]);
        }
    }
}
