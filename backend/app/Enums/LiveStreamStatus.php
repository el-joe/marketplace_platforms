<?php

namespace App\Enums;

enum LiveStreamStatus: string
{
    case Scheduled = 'scheduled';
    case Live      = 'live';
    case Ended     = 'ended';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Scheduled',
            self::Live      => 'Live',
            self::Ended     => 'Ended',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Scheduled => 'yellow',
            self::Live      => 'green',
            self::Ended     => 'gray',
        };
    }
}
