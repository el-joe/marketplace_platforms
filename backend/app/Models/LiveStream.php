<?php

namespace App\Models;

use App\Enums\LiveStreamStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LiveStream extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title_en', 'title_ar',
        'description_en', 'description_ar',
        'thumbnail_path',
        'status',
        'source',
        'media_path',
        'media_type',
        'duration_seconds',
        'scheduled_end_at',
        'scheduled_at',
        'started_at',
        'ended_at',
        'peak_viewers',
        'total_viewers',
        'likes_count',
        'stream_key',
        'media_mode',
        'created_by_admin_id',
    ];

    protected $casts = [
        'status' => LiveStreamStatus::class,
        'scheduled_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveStream $stream) {
            if (empty($stream->stream_key)) {
                $stream->stream_key = Str::random(32);
            }
        });
    }

    public function comments()
    {
        return $this->hasMany(LiveStreamComment::class);
    }

    public function isLive(): bool
    {
        return $this->status === LiveStreamStatus::Live;
    }

    public const SOURCE_LIVE = 'live';

    public const SOURCE_RECORDED = 'recorded';

    /** A scheduled audio/video file ("radio channel") that goes live and ends on its own. */
    public function isRecorded(): bool
    {
        return $this->source === self::SOURCE_RECORDED;
    }

    public function scopeRecorded($query)
    {
        return $query->where('source', self::SOURCE_RECORDED);
    }
}
