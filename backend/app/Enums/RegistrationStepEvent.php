<?php

namespace App\Enums;

enum RegistrationStepEvent: string
{
    case Viewed = 'viewed';
    case Completed = 'completed';
    case ValidationFailed = 'validation_failed';
}
