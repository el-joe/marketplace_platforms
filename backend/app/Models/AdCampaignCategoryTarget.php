<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignCategoryTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'ad_campaign_id',
        'category_id',
        'bid_override',
        'is_active',
    ];

    protected $casts = [
        'bid_override' => \App\Casts\DecimalMoney::class,
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
