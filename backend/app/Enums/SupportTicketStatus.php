<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SupportTicketStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
