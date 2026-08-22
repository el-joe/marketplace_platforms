<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BannerLinkType: string
{
    use EnumHelpers;

    case Url = 'url';
    case Product = 'product';
    case Category = 'category';
    case Brand = 'brand';
    case FlashSale = 'flash_sale';
    case Page = 'page';
    case None = 'none';
}
