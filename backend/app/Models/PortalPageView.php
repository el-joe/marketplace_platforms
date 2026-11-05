<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalPageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'visitor_id',
        'path',
        'route_name',
        'country_code',
        'is_new_visit',
        'viewed_at',
    ];

    protected $casts = [
        'is_new_visit' => 'boolean',
        'viewed_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(PortalVisitor::class, 'visitor_id');
    }
}
