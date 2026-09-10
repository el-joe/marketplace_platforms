<?php

namespace App\Services\Ads;

use App\Models\PaidAdBooking;
use Carbon\Carbon;

class AdStatsService
{
    /**
     * @return array{
     *   totals: array{impressions: int, clicks: int, ctr: float, spend: int, currency: string, ecpm: int, ecpc: int},
     *   daily: array<int, array{date: string, impressions: int, clicks: int, spend: int}>
     * }
     */
    public function forBooking(PaidAdBooking $b, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = $b->dailyStats()->orderBy('date');

        if ($from) {
            $query->whereDate('date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('date', '<=', $to->toDateString());
        }

        $stats = $query->get();

        $impressions = (int) $stats->sum('impressions');
        $clicks = (int) $stats->sum('clicks');
        $spend = (int) $stats->sum('spend');

        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        $ecpm = $impressions > 0 ? intdiv($spend * 1000, $impressions) : 0;
        $ecpc = $clicks > 0 ? intdiv($spend, $clicks) : 0;

        return [
            'totals' => [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
                'spend' => $spend,
                'currency' => $b->currency,
                'ecpm' => $ecpm,
                'ecpc' => $ecpc,
            ],
            'daily' => $stats->map(fn ($s) => [
                'date' => $s->date->toDateString(),
                'impressions' => $s->impressions,
                'clicks' => $s->clicks,
                'spend' => $s->spend,
            ])->values()->all(),
        ];
    }
}
