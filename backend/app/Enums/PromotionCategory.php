<?php

namespace App\Enums;

enum PromotionCategory: int
{
    case VendorRequests = 1;
    case PeerInfluencerProducts = 2;
    case AdminIntermediary = 3;
    case NawyProducts = 4;

    public function label(): string
    {
        return trans('enums/promotion_category.'.$this->name);
    }

    /** Contract default when the admin has not set a row: categories 1/2 = 3/4 ads, 3/4 = no obligation until configured. */
    public function defaultMinimum(): ?int
    {
        return match ($this) {
            self::VendorRequests => 3,
            self::PeerInfluencerProducts => 4,
            default => null,
        };
    }

    /** Categories an admin listing can be flagged with (admin-curated products). */
    public static function listingCategories(): array
    {
        return [self::AdminIntermediary, self::NawyProducts];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
