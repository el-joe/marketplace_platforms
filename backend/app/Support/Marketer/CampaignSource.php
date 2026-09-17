<?php

namespace App\Support\Marketer;

use InvalidArgumentException;

/**
 * enhancement.md P-14 task 1/2: the single listing a campaign promotes.
 * Exactly one of the four ids must be set — enforced at the DB level by
 * `chk_campaign_listing_xor_v2` on `marketer_campaigns` (see the migration
 * 2026_09_17_040000 docblock) AND re-validated here so a bad CampaignSource
 * fails fast with a clear message instead of a raw SQL constraint error.
 *
 * Travel/classified sources are schema-ready but NOT wired into
 * MarketerCampaignService::createCampaign()'s stock/price resolution the
 * same way product/admin-listing sources are — see the class docblock on
 * MarketerCampaignService for why (travel_packages/classified_listings
 * exist as models but have no product_variant/warehouse_inventory concept,
 * so CartLineSource (P-02/P-15) cannot resolve a purchase for them yet).
 * They are accepted here (and createMarketerListingFromInvitation() can
 * already build a MarketerListing row for them via createTravelListing()/
 * createClassifiedListing()) but are not reachable through any controller
 * in this prompt.
 */
final class CampaignSource
{
    private function __construct(
        public readonly string $category, // 'product' | 'travel' | 'classified'
        public readonly ?string $vendorListingId,
        public readonly ?string $adminListingId,
        public readonly ?string $travelPackageId,
        public readonly ?string $classifiedListingId,
    ) {
    }

    public static function vendorListing(string $vendorListingId): self
    {
        return new self('product', $vendorListingId, null, null, null);
    }

    public static function adminListing(string $adminListingId): self
    {
        return new self('product', null, $adminListingId, null, null);
    }

    public static function travelPackage(string $travelPackageId): self
    {
        return new self('travel', null, null, $travelPackageId, null);
    }

    public static function classifiedListing(string $classifiedListingId): self
    {
        return new self('classified', null, null, null, $classifiedListingId);
    }

    public function validate(): void
    {
        $set = array_filter([
            $this->vendorListingId,
            $this->adminListingId,
            $this->travelPackageId,
            $this->classifiedListingId,
        ]);

        if (count($set) !== 1) {
            throw new InvalidArgumentException(
                'A campaign source must set exactly one of vendor_listing_id, admin_listing_id, travel_package_id, classified_listing_id.'
            );
        }
    }

    public function isVendorListing(): bool
    {
        return $this->vendorListingId !== null;
    }

    public function isAdminListing(): bool
    {
        return $this->adminListingId !== null;
    }
}
