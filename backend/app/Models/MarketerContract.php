<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketerContract extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'current_version',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MarketerContractVersion::class);
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(MarketerContractVersion::class)
            ->where('is_active', true)
            ->latestOfMany('version_number');
    }
}
