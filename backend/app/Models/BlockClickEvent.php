<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockClickEvent extends Model
{
    use HasUuids;

    public const CREATED_AT = 'clicked_at';
    public const UPDATED_AT = null; // events are immutable

    protected $fillable = [
        'page_block_id',
        'user_id',
        'session_id',
        'click_target',
        'click_target_type',
        'device_type',
        'country_id',
        'ip_address',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
