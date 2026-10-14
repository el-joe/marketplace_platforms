<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCampaignFeeAccrual extends Model
{
    use HasUuids;

    public const STATUS_ACCRUED = 'accrued';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'campaign_id', 'invitation_id', 'conversion_id', 'order_id', 'order_item_id',
        'quantity', 'line_total', 'commission_base',
        'marketing_fixed_delta', 'marketing_variable_delta', 'marketing_total_delta',
        'running_base', 'running_marketing_total',
        'status', 'settled_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'line_total' => DecimalMoney::class,
        'commission_base' => DecimalMoney::class,
        'marketing_fixed_delta' => DecimalMoney::class,
        'marketing_variable_delta' => DecimalMoney::class,
        'marketing_total_delta' => DecimalMoney::class,
        'running_base' => DecimalMoney::class,
        'running_marketing_total' => DecimalMoney::class,
        'settled_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'campaign_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class, 'invitation_id');
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignConversion::class, 'conversion_id');
    }
}
