<?php

namespace App\Support\Marketer;

use App\Models\Marketer;
use App\Models\TravelAgency;
use App\Models\Vendor;

/**
 * enhancement.md P-14 task 2: who a campaign belongs to, and therefore who
 * pays marketer commission (D5) — the vendor for vendor-listing campaigns,
 * the platform for admin-listing campaigns. A 'marketer' owner only exists
 * transiently while a marketer-originated request awaits approval by the
 * real owner (see CampaignSource::owner() and
 * MarketerCampaignService::requestCampaign()/approveMarketerRequest()).
 */
final class CampaignOwner
{
    private function __construct(
        public readonly string $type, // 'vendor' | 'platform' | 'marketer' | 'travel_agency'
        public readonly ?string $id,
    ) {
    }

    public static function vendor(Vendor|string $vendor): self
    {
        return new self('vendor', is_string($vendor) ? $vendor : $vendor->id);
    }

    /** A travel agency promoting its own trips (owner_type travel_agency, owner_id = agency id, no vendor). */
    public static function travelAgency(TravelAgency|string $agency): self
    {
        return new self('travel_agency', is_string($agency) ? $agency : $agency->id);
    }

    public static function platform(): self
    {
        return new self('platform', null);
    }

    public static function marketer(Marketer|string $marketer): self
    {
        return new self('marketer', is_string($marketer) ? $marketer : $marketer->id);
    }

    public function isVendor(): bool
    {
        return $this->type === 'vendor';
    }

    public function isTravelAgency(): bool
    {
        return $this->type === 'travel_agency';
    }

    /** Vendors and travel agencies fund their own campaigns (budget + campaign fee); the platform and marketers do not. */
    public function isOwnerFunded(): bool
    {
        return $this->isVendor() || $this->isTravelAgency();
    }

    public function isPlatform(): bool
    {
        return $this->type === 'platform';
    }

    public function isMarketer(): bool
    {
        return $this->type === 'marketer';
    }
}
