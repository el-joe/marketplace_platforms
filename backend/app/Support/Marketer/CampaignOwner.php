<?php

namespace App\Support\Marketer;

use App\Models\Marketer;
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
        public readonly string $type, // 'vendor' | 'platform' | 'marketer'
        public readonly ?string $id,
    ) {
    }

    public static function vendor(Vendor|string $vendor): self
    {
        return new self('vendor', is_string($vendor) ? $vendor : $vendor->id);
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

    public function isPlatform(): bool
    {
        return $this->type === 'platform';
    }

    public function isMarketer(): bool
    {
        return $this->type === 'marketer';
    }
}
