<?php

namespace App\Models;

use App\Enums\AiVideoGenerationJobRequestedByType;
use App\Enums\AiVideoGenerationJobStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVideoGenerationJob extends Model
{
    use HasUuids;

    protected $table = 'ai_video_generation_jobs';

    protected $fillable = [
        'requested_by_type',
        'requested_by_id',
        'vendor_listing_id',
        'prompt',
        'source_images',
        'result_video_path',
        'duration_seconds',
        'status',
        'provider',
        'credits_consumed',
        'error_message',
    ];

    protected $casts = [
        'requested_by_type' => AiVideoGenerationJobRequestedByType::class,
        'status'            => AiVideoGenerationJobStatus::class,
        'source_images'     => 'array',
    ];

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function scopeForOwner($query, string $type, string $id)
    {
        return $query->where('requested_by_type', $type)->where('requested_by_id', $id);
    }

    public function isCompleted(): bool
    {
        return $this->status === AiVideoGenerationJobStatus::Completed;
    }
}
