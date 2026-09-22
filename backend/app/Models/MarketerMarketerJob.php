<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerMarketerJob extends Model
{
    use HasUuids;

    protected $table = 'marketer_marketer_job';

    protected $fillable = [
        'marketer_id',
        'marketer_job_id',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function marketerJob(): BelongsTo
    {
        return $this->belongsTo(MarketerJob::class);
    }

    public function categoryScopes(): HasMany
    {
        return $this->hasMany(MarketerMarketerJobCategory::class, 'marketer_marketer_job_id');
    }
}
