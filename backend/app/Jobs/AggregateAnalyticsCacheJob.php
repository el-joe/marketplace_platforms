<?php

namespace App\Jobs;

use App\Models\Country;
use App\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AggregateAnalyticsCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    private const PERIODS = ['today', 'week', 'month', 'quarter', 'year'];

    private const METHODS = [
        'overview',
        'revenueChart',
        'ordersByStatus',
        'ordersByPaymentMethod',
        'topProducts',
        'topVendors',
        'topCategories',
        'slaMetrics',
        'adPerformance',
        'flashSaleAnalytics',
        'returnAnalytics',
        'supportMetrics',
    ];

    public function handle(AnalyticsService $service): void
    {
        $countryIds = Country::where('is_active', true)->pluck('id')->toArray();
        // Also pre-warm for "all countries" (empty country_id)
        array_unshift($countryIds, null);

        foreach (self::PERIODS as $period) {
            foreach ($countryIds as $countryId) {
                foreach (self::METHODS as $method) {
                    try {
                        $request = $this->buildRequest($period, $countryId);
                        $service->{$method}($request);
                    } catch (\Throwable $e) {
                        Log::warning("AggregateAnalyticsCacheJob: failed {$method}/{$period}", [
                            'error' => $e->getMessage(),
                            'country_id' => $countryId,
                        ]);
                    }
                }
            }
        }
    }

    private function buildRequest(string $period, ?string $countryId): Request
    {
        $data = ['period' => $period];
        if ($countryId) {
            $data['country_id'] = $countryId;
        }

        $request = Request::create('/', 'GET', $data);

        return $request;
    }
}
