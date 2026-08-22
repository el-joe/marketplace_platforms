<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Requester type for ai_video_generation_jobs.requested_by_type.
 *
 * NOTE: distinct from AiJobRequestedByType (used by ai_image_enhancement_jobs).
 */
enum AiVideoGenerationJobRequestedByType: string
{
    use EnumHelpers;

    case Vendor = 'vendor';
}
