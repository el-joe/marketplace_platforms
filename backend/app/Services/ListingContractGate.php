<?php

namespace App\Services;

use App\Exceptions\ContractNotConfiguredException;
use App\Exceptions\ContractRequiredException;
use App\Models\VendorListing;
use App\Services\Vendor\CategoryContractService;

/**
 * Companion of {@see ListingCertificationGate}: a listing may only go live while the seller is covered by every
 * individual contract its product requires. A product that requires none is covered by the global Seller Agreement
 * alone and is never asked for a signature here. Existing active listings are never switched off by this gate; it
 * applies when a listing is created, re-activated or approved.
 */
class ListingContractGate
{
    /**
     * @throws ContractRequiredException the seller still has to sign the individual contract
     * @throws ContractNotConfiguredException the required contract is not configured for the seller's country
     */
    public static function assertCanGoLive(VendorListing $listing): void
    {
        $listing->loadMissing('vendor', 'productVariant.product');

        $vendor = $listing->vendor;
        $product = $listing->productVariant?->product;

        if (! $vendor || ! $product) {
            return;
        }

        app(CategoryContractService::class)->assertCoveredForProduct($vendor, $product);
    }
}
