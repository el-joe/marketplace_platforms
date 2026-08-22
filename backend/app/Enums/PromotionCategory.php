<?php

namespace App\Enums;

enum PromotionCategory: int
{
    case VendorRequests = 1;
    case PeerInfluencerProducts = 2;
    case AdminIntermediary = 3;

    public function label(): string
    {
        return trans('enums/promotion_category.' . $this->name);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
