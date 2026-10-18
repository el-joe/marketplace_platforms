<?php

namespace App\Enums;

enum LiveStreamMediaMode: string
{
    case Audio = 'audio';
    case Video = 'video';
    case Both = 'both';

    /** @return array{mode: string, audio: bool, video: bool} */
    public function state(): array
    {
        return [
            'mode' => $this->value,
            'audio' => $this !== self::Video,
            'video' => $this !== self::Audio,
        ];
    }
}
