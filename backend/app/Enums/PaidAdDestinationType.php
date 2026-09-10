<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdDestinationType: string
{
    use EnumHelpers;

    case Listing = 'listing';
    case ClassifiedListing = 'classified_listing';
    case Store = 'store';
    case Brand = 'brand';
    case Category = 'category';
    case MarketerProfile = 'marketer_profile';
    case Campaign = 'campaign';
    case External = 'external';
}
