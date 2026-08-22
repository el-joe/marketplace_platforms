<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CampaignType: string
{
    use EnumHelpers;

    case ProductPromotion = 'product_promotion';
    case StorePromotion = 'store_promotion';
    case CategoryPromotion = 'category_promotion';
    case BrandDeal = 'brand_deal';
    case ProductSpecific = 'product_specific';
    case FlashSale = 'flash_sale';
    case General = 'general';
    case ClassifiedPromotion = 'classified_promotion';
    case TravelPromotion = 'travel_promotion';
    case ReferralLink = 'referral_link';
    case DiscountCode = 'discount_code';
}
