<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockAnalytic extends Model
{
    use HasUuids;

    protected $table = 'block_analytics';

    protected $fillable = [
        'page_block_id',
        'page_id',
        'country_id',
        'date',
        'impressions',
        'clicks',
        'unique_visitors',
        'add_to_cart_count',
        'orders_attributed',
        'revenue_attributed',
        'ctr',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'unique_visitors' => 'integer',
        'add_to_cart_count' => 'integer',
        'orders_attributed' => 'integer',
        'revenue_attributed' => 'integer',
        'ctr' => 'decimal:4',
    ];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
