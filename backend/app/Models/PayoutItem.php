<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'payout_id',
        'item_type',
        'sub_order_id',
        'marketer_conversion_id',
        'sample_item_id',
        'campaign_sample_id',
        'invitation_id',
        'campaign_id',
        'vat_amount',
        'marketing_fee_amount',
        'gross',
        'commission',
        'net',
        'description',
    ];

    protected $casts = [
        'vat_amount' => DecimalMoney::class,
        'marketing_fee_amount' => DecimalMoney::class,
        'gross' => DecimalMoney::class,
        'commission' => DecimalMoney::class,
        'net' => DecimalMoney::class,
    ];

    public const TYPE_CAMPAIGN_FEE = 'campaign_fee';

    public const TYPE_CAMPAIGN_BUDGET_COMMISSION = 'campaign_budget_commission';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'campaign_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function marketerConversion(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignConversion::class);
    }

    public function campaignSample(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignSample::class, 'campaign_sample_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class, 'invitation_id');
    }


    public function sampleItem(): BelongsTo
    {
        return $this->belongsTo(MarketerSampleItem::class, 'sample_item_id');
    }
}
