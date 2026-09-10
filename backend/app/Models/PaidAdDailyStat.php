<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidAdDailyStat extends Model
{
    use HasUuids;

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'spend' => 'integer',
    ];

    protected $fillable = [
        'paid_ad_booking_id',
        'date',
        'impressions',
        'clicks',
        'spend',
        'currency',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(PaidAdBooking::class, 'paid_ad_booking_id');
    }
}
