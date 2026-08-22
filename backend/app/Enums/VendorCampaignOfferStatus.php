<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VendorCampaignOfferStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingAdmin = 'pending_admin';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
