<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCampaignConversion extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'invitation_id', 'order_id', 'order_item_id',
        'referral_clicked_at', 'commission_amount', 'currency',
        'commissioned', 'paid_at', 'sale_number_in_campaign', 'tiered_rule_id',
    ];

    protected $casts = [
        'referral_clicked_at' => 'datetime',
        'paid_at' => 'datetime',
        'commissioned' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tieredRule(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignTieredRule::class);
    }
}
