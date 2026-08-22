<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Country;
use Illuminate\Bus\Queueable;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateBestSellerRankingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    private const PERIOD = 'monthly';
    private const TOP_N = 100;

    public function __construct(private readonly ?OutputStyle $output = null)
    {
        $this->queue = 'rankings';
    }

    public function handle(): void
    {
        Log::info('RecalculateBestSellerRankingsJob: starting');

        $categories = Category::query()
            ->whereNull('deleted_at')
            ->select('id', 'lft', 'rgt')
            ->get();

        $countries = Country::query()->where('is_active', true)->get();

        foreach ($countries as $country) {
            $this->recalculateForCountry($country, $categories);
        }

        Cache::tags('bestseller')->flush();

        Log::info('RecalculateBestSellerRankingsJob: done');
    }

    private function recalculateForCountry(Country $country, $categories): void
    {
        $this->line("Recalculating rankings for country {$country->id}...");

        $productSales = DB::table('vendor_listings as vl')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('vl.country_id', $country->id)
            ->where('vl.status', 'active')
            ->select('p.id as product_id', 'p.category_id', DB::raw('SUM(vl.total_sold) as units_sold'))
            ->groupBy('p.id', 'p.category_id')
            ->get();

        if ($productSales->isEmpty()) {
            return;
        }

        $categoriesById = $categories->keyBy('id');

        // For each product, roll units_sold up into its own category plus every ancestor category.
        $categoryTotals = [];
        foreach ($productSales as $row) {
            $category = $categoriesById->get($row->category_id);
            if ($category === null) {
                continue;
            }

            foreach ($categories as $candidate) {
                if ($candidate->lft <= $category->lft && $candidate->rgt >= $category->rgt) {
                    $categoryTotals[$candidate->id][$row->product_id] ??= 0;
                    $categoryTotals[$candidate->id][$row->product_id] += (int) $row->units_sold;
                }
            }
        }

        $now = now();
        $rows = [];

        foreach ($categoryTotals as $categoryId => $productUnits) {
            arsort($productUnits);

            $rank = 0;
            foreach (array_slice($productUnits, 0, self::TOP_N, true) as $productId => $unitsSold) {
                $rank++;
                $rows[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'product_id' => $productId,
                    'category_id' => $categoryId,
                    'country_id' => $country->id,
                    'rank' => $rank,
                    'units_sold' => $unitsSold,
                    'period' => self::PERIOD,
                    'calculated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_bestseller_rankings')->upsert(
                $chunk,
                ['product_id', 'category_id', 'country_id', 'period'],
                ['rank', 'units_sold', 'calculated_at', 'updated_at']
            );
        }

        DB::table('product_bestseller_rankings')
            ->where('country_id', $country->id)
            ->where('period', self::PERIOD)
            ->where('calculated_at', '<', $now->copy()->subDays(7))
            ->delete();

        $this->line("  {$country->id}: " . count($rows) . ' ranking rows upserted.');
    }

    private function line(string $message): void
    {
        if ($this->output !== null) {
            $this->output->writeln($message);
        }
    }
}
