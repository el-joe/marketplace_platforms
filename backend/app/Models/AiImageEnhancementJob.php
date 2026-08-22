<?php

namespace App\Models;

use App\Enums\AiImageEnhancementJobStatus;
use App\Enums\AiJobRequestedByType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiImageEnhancementJob extends Model
{
    use HasUuids;

    protected $table = 'ai_image_enhancement_jobs';

    protected $fillable = [
        'product_image_id',
        'original_path',
        'enhanced_path',
        'status',
        'provider',
        'error_message',
        'requested_by_type',
        'requested_by_id',
        'applied',
    ];

    protected $casts = [
        'status'             => AiImageEnhancementJobStatus::class,
        'requested_by_type'  => AiJobRequestedByType::class,
        'applied'            => 'boolean',
    ];

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['queued', 'processing']);
    }

    public function scopeForOwner($query, string $type, string $id)
    {
        return $query->where('requested_by_type', $type)->where('requested_by_id', $id);
    }

    public function isCompleted(): bool
    {
        return $this->status === AiImageEnhancementJobStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === AiImageEnhancementJobStatus::Failed;
    }
}
