<?php

namespace App\Jobs;

use App\Models\VirtualTryonSession;
use App\Models\ProductImage;
use App\Services\AiProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessVirtualTryOnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly VirtualTryonSession $session,
        public readonly string $productImagePath,
    ) {
    }

    public function handle(): void
    {
        $this->session->update(['status' => 'processing']);

        try {
            $provider = AiProviderFactory::make();

            $resultPath = $provider->generateTryOn(
                $this->session->customer_photo_path,
                $this->productImagePath
            );

            $this->session->update([
                'status'            => 'completed',
                'result_image_path' => $resultPath,
                'provider'          => class_basename($provider),
            ]);

            // TODO: notify customer if they are logged in
            // Notification::send($this->session->customer, new TryOnReadyNotification($this->session));
        } catch (Throwable $e) {
            Log::error('AI virtual try-on failed', [
                'session_id' => $this->session->id,
                'error'      => $e->getMessage(),
            ]);

            $this->session->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
