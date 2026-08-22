<?php

namespace App\Jobs;

use App\Models\Country;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProductCountrySettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $productId)
    {
    }

    public function handle(): void
    {
        $countries = Country::where('is_launched', true)->get(['id']);

        $rows = $countries->map(fn($c) => [
            'id' => (string) Str::uuid(),
            'product_id' => $this->productId,
            'country_id' => $c->id,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('product_country_settings')->insertOrIgnore($chunk);
        }
    }
}
