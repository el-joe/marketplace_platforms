<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestResult extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // rows are immutable once written

    protected $fillable = [
        'ab_test_id',
        'variant',
        'date',
        'visitors',
        'impressions',
        'clicks',
        'add_to_cart_count',
        'orders',
        'revenue',
        'conversion_rate',
        'revenue_per_visitor',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'date' => 'date',
        'visitors' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'add_to_cart_count' => 'integer',
        'orders' => 'integer',
        'revenue' => 'integer',
        'conversion_rate' => 'decimal:4',
        'revenue_per_visitor' => 'integer',
    ];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTest::class);
    }
}
