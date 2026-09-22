<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single include/exclude entry for a campaign's category scoping
 * (`marketer_campaigns.product_category_selection_mode` /
 * `classified_category_selection_mode`). Exactly one of category_id /
 * classified_category_id is set per row.
 */
class MarketerCampaignCategoryRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_campaign_id',
        'category_id',
        'classified_category_id',
        'mode',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'marketer_campaign_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class);
    }
}
