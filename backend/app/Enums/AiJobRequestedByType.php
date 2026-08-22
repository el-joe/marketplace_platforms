<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Requester type for ai_image_enhancement_jobs.requested_by_type.
 *
 * NOTE: ai_video_generation_jobs.requested_by_type uses a different value
 * set (vendor/marketer, no admin) — see AiVideoGenerationJobRequestedByType.
 */
enum AiJobRequestedByType: string
{
    use EnumHelpers;

    case Vendor = 'vendor';
    case Admin = 'admin';
}
