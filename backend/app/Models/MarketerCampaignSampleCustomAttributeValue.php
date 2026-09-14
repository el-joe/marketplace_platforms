<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCampaignSampleCustomAttributeValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_campaign_sample_id',
        'product_custom_attribute_id',
        'label',
        'unit',
        'value',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignSample::class, 'marketer_campaign_sample_id');
    }

    public function productCustomAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductCustomAttribute::class);
    }
}
