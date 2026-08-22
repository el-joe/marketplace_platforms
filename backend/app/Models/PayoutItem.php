<?php

namespace App\Models;

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
        'gross',
        'commission',
        'net',
        'description',
    ];

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
        return $this->belongsTo(\App\Models\MarketerCampaignConversion::class);
    }

    public function sampleItem(): BelongsTo
    {
        return $this->belongsTo(MarketerSampleItem::class, 'sample_item_id');
    }
}
