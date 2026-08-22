<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassifiedListingImage extends Model
{
    use HasUuids;

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'classified_listing_id', 'file_path', 'position', 'is_primary',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }
}
