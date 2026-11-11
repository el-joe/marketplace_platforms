<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeGuideRow extends Model
{
    use HasUuids;

    protected $fillable = [
        'size_guide_id',
        'size_label',
        'values',
        'sort_order',
    ];

    protected $casts = [
        'values' => 'array',
        'sort_order' => 'integer',
    ];

    public function sizeGuide(): BelongsTo
    {
        return $this->belongsTo(SizeGuide::class);
    }
}
