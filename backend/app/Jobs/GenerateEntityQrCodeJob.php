<?php

namespace App\Jobs;

use App\Services\BrandedQrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates a branded QR code PNG for a product, vendor, or marketer profile
 * and stores it on the public disk, then writes the path back to the model.
 *
 * @param  Model  $model  The entity (Product, Vendor, MarketerProfile)
 * @param  string  $scanUrl  The URL that will be encoded in the QR
 * @param  string  $label  Text rendered below the QR image
 * @param  string  $storagePath  Relative path under storage/app/public/
 */
class GenerateEntityQrCodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly Model $model,
        private readonly string $scanUrl,
        private readonly string $label,
        private readonly string $storagePath,
    ) {}

    public function handle(BrandedQrService $qrService): void
    {
        $path = $qrService->generateAndStore($this->scanUrl, $this->label, $this->storagePath);

        // Use updateQuietly so we don't re-trigger the observer and cause a loop
        $this->model->updateQuietly(['qr_code_path' => $path]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateEntityQrCodeJob failed', [
            'model' => get_class($this->model),
            'id' => $this->model->getKey(),
            'error' => $e->getMessage(),
        ]);
    }
}
