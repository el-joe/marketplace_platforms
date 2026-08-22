<?php

namespace App\Jobs;

use App\Models\AiImageEnhancementJob;
use App\Services\AiProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImageEnhancementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly AiImageEnhancementJob $job)
    {
    }

    public function handle(): void
    {
        $this->job->update(['status' => 'processing']);

        try {
            $provider = AiProviderFactory::make();

            $enhancedPath = $provider->enhanceImage($this->job->original_path);

            $this->job->update([
                'status'        => 'completed',
                'enhanced_path' => $enhancedPath,
                'provider'      => class_basename($provider),
            ]);

            // TODO: notify requester (vendor or admin) via notification
            // Notification::send($requester, new ImageEnhancedNotification($this->job));
        } catch (Throwable $e) {
            Log::error('AI image enhancement failed', [
                'job_id' => $this->job->id,
                'error'  => $e->getMessage(),
            ]);

            $this->job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
