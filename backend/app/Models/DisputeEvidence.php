<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DisputeEvidence extends Model
{
    protected $fillable = [
        'dispute_id',
        'uploaded_by_user_id',
        'media_id',
        'description',
        'created_at',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_user_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
