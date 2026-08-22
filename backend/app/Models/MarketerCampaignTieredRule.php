<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCampaignTieredRule extends Model
{
    use HasUuids;

    protected $fillable = ['campaign_id', 'from_sale_number', 'commission_amount', 'currency', 'sort_order'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }
}
