<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CityImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $filePath,
        public readonly string $adminId,
    ) {
    }

    public function handle(): void
    {
        // TODO: Implement CSV import
        // Expected columns: name_en, name_ar, country_id (or iso_code_2), lat, lng
        // Create City records for each valid row
    }
}
