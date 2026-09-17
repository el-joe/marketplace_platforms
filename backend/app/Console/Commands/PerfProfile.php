<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-22 task 4: replay a fixed list of storefront endpoints
 * through the real HTTP kernel (same "scratchpad/profile.php" approach used
 * for the original audit) with the query log enabled, and print time, query
 * count, the slowest query, and any duplicate query signatures per endpoint.
 *
 * Each endpoint has a documented query/time budget (reusing the ceilings
 * P-19/P-20/P-21/P-22 established in their own tests where an endpoint has
 * one; a conservative default otherwise). Exits non-zero (for CI) if any
 * endpoint exceeds its budget, unless --report-only is passed.
 */
class PerfProfile extends Command
{
    protected $signature = 'perf:profile
        {--site-code= : country site_code to use in the URL (defaults to the first active country)}
        {--report-only : always exit 0, even if a budget is exceeded}';

    protected $description = 'Replay storefront endpoints through the HTTP kernel and report query count / timing per endpoint (enhancement.md P-22)';

    /**
     * @var array<string, array{method: string, path: string, query_budget: int, ms_budget: int}>
     */
    private const ENDPOINTS = [
        'home' => ['path' => '{site}/home', 'query_budget' => 25, 'ms_budget' => 500],
        'categories' => ['path' => '{site}/categories', 'query_budget' => 6, 'ms_budget' => 200],
        'category_products' => ['path' => '{site}/browse/product/{category}', 'query_budget' => 30, 'ms_budget' => 500],
        'search' => ['path' => '{site}/search?q=a', 'query_budget' => 20, 'ms_budget' => 500],
        'suggestions' => ['path' => '{site}/search?q=a', 'query_budget' => 20, 'ms_budget' => 500],
        'pdp' => ['path' => '{site}/l/{listing}', 'query_budget' => 55, 'ms_budget' => 500],
        'cart' => ['path' => '{site}/cart', 'query_budget' => 15, 'ms_budget' => 500],
        'checkout_prepare' => ['path' => '{site}/checkout/shipping-methods', 'query_budget' => 20, 'ms_budget' => 500],
        'orders_list' => ['path' => '{site}/orders', 'query_budget' => 15, 'ms_budget' => 500],
    ];

    public function handle(): int
    {
        $country = \App\Models\Country::query()->where('is_active', true)->first();

        if (!$country) {
            $this->error('No active country found — run PerformanceDatasetSeeder or a scenario builder first.');
            return self::FAILURE;
        }

        $siteCode = $this->option('site-code') ?: $country->site_code;
        $category = \App\Models\Category::query()->first();
        $listing = \App\Models\VendorListing::query()->where('status', 'active')->first();

        $rows = [];
        $anyOverBudget = false;

        foreach (self::ENDPOINTS as $name => $spec) {
            $path = str_replace(
                ['{site}', '{category}', '{listing}'],
                [$siteCode, $category?->id ?? 'missing', $listing?->id ?? 'missing'],
                $spec['path']
            );

            if (str_contains($path, 'missing')) {
                $rows[] = [$name, 'SKIPPED (no fixture data)', '-', '-', '-'];
                continue;
            }

            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);
            $response = $this->replay($path);
            $elapsedMs = (microtime(true) - $start) * 1000;

            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            $queryCount = count($queries);
            $signatures = array_map(fn ($q) => $q['query'], $queries);
            $duplicateCount = $queryCount - count(array_unique($signatures));

            $slowest = collect($queries)->sortByDesc('time')->first();

            $overBudget = $queryCount > $spec['query_budget'] || $elapsedMs > $spec['ms_budget'];
            $anyOverBudget = $anyOverBudget || $overBudget;

            $rows[] = [
                $name,
                $response->getStatusCode(),
                sprintf('%.1fms / %dms budget', $elapsedMs, $spec['ms_budget']),
                "{$queryCount} / {$spec['query_budget']} budget" . ($overBudget ? ' [OVER]' : ''),
                $duplicateCount > 0 ? "{$duplicateCount} duplicate(s)" : 'none',
            ];

            if ($slowest) {
                $this->line("  [{$name}] slowest query ({$slowest['time']}ms): " . substr($slowest['query'], 0, 160));
            }
        }

        $this->table(['Endpoint', 'Status', 'Time', 'Queries', 'Duplicates'], $rows);

        if ($anyOverBudget) {
            $this->error('One or more endpoints exceeded their query/time budget.');
            return $this->option('report-only') ? self::SUCCESS : self::FAILURE;
        }

        $this->info('All endpoints within budget.');
        return self::SUCCESS;
    }

    private function replay(string $path): \Symfony\Component\HttpFoundation\Response
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

        $request = Request::create('/api/customer/v1/' . ltrim($path, '/'), 'GET');

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
