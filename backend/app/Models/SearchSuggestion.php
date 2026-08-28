<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchSuggestion extends Model
{
    use HasUuids;

    protected $fillable = [
        'keyword',
        'keyword_normalized',
        'country_id',
        'search_count',
        'is_pinned',
        'is_blocked',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'is_blocked'   => 'boolean',
        'search_count' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
