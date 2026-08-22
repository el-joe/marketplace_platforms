<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches latest exchange rates from the configured API and updates the
 * currencies table, skipping any rows that have been manually overridden
 * by an admin (is_manually_overridden = true).
 *
 * Scheduled daily via routes/console.php:
 *   Schedule::job(UpdateExchangeRatesJob::class)->dailyAt('02:00');
 *
 * Configure in config/services.php:
 *   'exchange_rates' => [
 *       'url'      => env('EXCHANGE_RATES_URL', 'https://api.exchangerate-api.com/v4/latest/USD'),
 *       'api_key'  => env('EXCHANGE_RATES_KEY'),
 *       'base'     => env('EXCHANGE_RATES_BASE', 'USD'),
 *   ]
 */
class UpdateExchangeRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function handle(): void
    {
        $config = config('services.exchange_rates', []);
        $url = $config['url'] ?? 'https://api.exchangerate-api.com/v4/latest/USD';
        $apiKey = $config['api_key'] ?? null;
        $base = strtoupper($config['base'] ?? 'USD');

        $params = ['base' => $base];
        if ($apiKey) {
            $params['access_key'] = $apiKey;
        }

        $response = Http::timeout(20)->get($url, $params);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Exchange rate API returned HTTP {$response->status()}: " . $response->body()
            );
        }

        $data = $response->json();
        $rates = $data['rates'] ?? $data['conversion_rates'] ?? [];

        if (empty($rates)) {
            throw new \RuntimeException('Exchange rate API returned no rates. Response: ' . $response->body());
        }

        $now = now();
        $updated = 0;
        $skipped = 0;

        foreach ($rates as $code => $rate) {
            $code = strtoupper((string) $code);
            $rate = (float) $rate;

            if ($rate <= 0) {
                continue;
            }

            $rows = DB::table('currencies')
                ->where('code', $code)
                ->where('is_manually_overridden', false)
                ->update([
                    'exchange_rate_to_base' => $rate,
                    'base_currency_code' => $base,
                    'rate_updated_at' => $now,
                    'updated_at' => $now,
                ]);

            $updated += $rows;
            if ($rows === 0) {
                $skipped++;
            }
        }

        Log::info("UpdateExchangeRatesJob: {$updated} rates updated, {$skipped} codes skipped (not in DB or manually overridden).");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('UpdateExchangeRatesJob failed: ' . $e->getMessage());
    }
}
