<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BannerAudience: string
{
    use EnumHelpers;

    case All = 'all';
    case Guest = 'guest';
    case LoggedIn = 'logged_in';
    case Vip = 'vip';
    case NewUser = 'new_user';
}
