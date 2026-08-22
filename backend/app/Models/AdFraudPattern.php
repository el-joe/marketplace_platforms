<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdFraudPattern extends Model
{
    use HasUuids;

    protected $fillable = [
        'ip_address',
        'ad_campaign_id',
        'clicks_last_hour',
        'clicks_last_24h',
        'is_blocked',
        'blocked_at',
        'block_reason',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
