<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TravelBookingStatus: string
{
    use EnumHelpers;

    case PendingDocuments = 'pending_documents';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
