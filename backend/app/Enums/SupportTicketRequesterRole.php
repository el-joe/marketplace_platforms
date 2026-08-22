<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SupportTicketRequesterRole: string
{
    use EnumHelpers;

    case Customer = 'customer';
    case Seller = 'seller';
    case DeliveryAgent = 'delivery_agent';
    case ShippingSupervisor = 'shipping_supervisor';
    case TravelAgency = 'travel_agency';
}
