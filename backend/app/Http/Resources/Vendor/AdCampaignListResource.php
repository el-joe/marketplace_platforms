<?php

namespace App\Http\Resources\Vendor;

use App\Enums\AdCampaignStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdCampaignListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // today_stats is eager-loaded as a single AdDailyStat (latest per day)
        $today = $this->relationLoaded('todayStats') ? $this->todayStats->first() : null;

        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'type'                => $this->type?->value,
            'status'              => $this->status?->value,
            'targeting_type'      => $this->targeting_type?->value,
            'budget_total'        => (float) $this->budget_total,
            'budget_daily'        => $this->budget_daily !== null ? (float) $this->budget_daily : null,
            'budget_spent_total'  => (float) $this->budget_spent_total,
            'budget_spent_today'  => (float) $this->budget_spent_today,
            'bid'                 => (float) $this->bid,
            'quality_score'       => $this->quality_score !== null ? (float) $this->quality_score : null,
            'starts_at'           => $this->starts_at?->toISOString(),
            'ends_at'             => $this->ends_at?->toISOString(),
            'rejection_reason'    => $this->when($this->status === AdCampaignStatus::Rejected, $this->rejection_reason),
            // today at-a-glance
            'today_impressions'   => $today?->impressions ?? 0,
            'today_clicks'        => $today?->clicks ?? 0,
            'today_spend'         => $today?->spend ?? 0,
            'today_ctr'           => $today ? (float) $today->ctr : null,
        ];
    }
}
