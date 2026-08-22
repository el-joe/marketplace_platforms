<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryAgentDocumentType: string
{
    use EnumHelpers;

    case NationalId = 'national_id';
    case DrivingLicense = 'driving_license';
    case VehicleRegistration = 'vehicle_registration';
    case Insurance = 'insurance';
    case ProfilePhoto = 'profile_photo';
}
