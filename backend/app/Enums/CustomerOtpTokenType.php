<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CustomerOtpTokenType: string
{
    use EnumHelpers;

    case EmailVerification = 'email_verification';
    case PhoneVerification = 'phone_verification';
    case PasswordReset = 'password_reset';
}
