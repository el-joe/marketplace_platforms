<?php

namespace App\Services\Vendor;

use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\AdQualityScore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AdPerformanceService
{
    public function getStats(AdCampaign $campaign, ?string $from, ?string $to, bool $perListing = false): array
    {
        $dateFrom = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(29)->startOfDay();
        $dateTo   = $to   ? Carbon::parse($to)->endOfDay()   : now()->endOfDay();

        $query = AdDailyStat::where('ad_campaign_id', $campaign->id)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('date');

        if (!$perListing) {
            $query->whereNull('vendor_listing_id');
        }

        $rows = $query->get();

        $totals = [
            'impressions'              => $rows->sum('impressions'),
            'clicks'                   => $rows->sum('clicks'),
            'valid_clicks'             => $rows->sum('valid_clicks'),
            'conversions'              => $rows->sum('conversions'),
            'spend'              => $rows->sum('spend'),
            'revenue_attributed' => $rows->sum('revenue_attributed'),
        ];

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to'   => $dateTo->toDateString(),
            'totals'    => $totals,
            'rows'      => $rows,
        ];
    }

    public function getQualityScore(AdCampaign $campaign): ?AdQualityScore
    {
        return AdQualityScore::where('ad_campaign_id', $campaign->id)
            ->latest('calculated_at')
            ->first();
    }

    public function getQualityScoreHistory(AdCampaign $campaign, int $limit = 30): Collection
    {
        return AdQualityScore::where('ad_campaign_id', $campaign->id)
            ->orderByDesc('calculated_at')
            ->limit($limit)
            ->get();
    }
}
