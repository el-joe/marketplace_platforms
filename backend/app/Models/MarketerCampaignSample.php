<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCampaignSample extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'invitation_id', 'sample_owner', 'quantity',
        'status', 'dispatched_at', 'delivered_at', 'delivery_address_snapshot',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_address_snapshot' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class);
    }
}
