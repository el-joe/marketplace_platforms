<?php

namespace App\Models;

use App\Enums\AiFeatureCreditOwnerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiFeatureCredit extends Model
{
    use HasUuids;

    const FEATURE_IMAGE_ENHANCEMENT = 'image_enhancement';
    const FEATURE_VIRTUAL_TRYON     = 'virtual_tryon';
    const FEATURE_VIDEO_GENERATION  = 'video_generation';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'feature',
        'credits_remaining',
        'credits_used_total',
        'reset_at',
    ];

    protected $casts = [
        'owner_type' => AiFeatureCreditOwnerType::class,
        'reset_at'   => 'date',
    ];

    public static function balanceFor(string $ownerType, string $ownerId, string $feature): self
    {
        return static::firstOrCreate(
            ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'feature' => $feature],
            ['credits_remaining' => 0, 'credits_used_total' => 0]
        );
    }

    public function hasCredits(int $required = 1): bool
    {
        return $this->credits_remaining >= $required;
    }

    public function consume(int $amount = 1): void
    {
        $this->decrement('credits_remaining', $amount);
        $this->increment('credits_used_total', $amount);
    }

    public function topUp(int $amount): void
    {
        $this->increment('credits_remaining', $amount);
    }
}
