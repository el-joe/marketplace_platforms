<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlockSeller extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'page_block_id',
        'seller_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'seller_id');
    }
}
