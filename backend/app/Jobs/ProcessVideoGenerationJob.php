<?php

namespace App\Jobs;

use App\Models\AiVideoGenerationJob;
use App\Services\AiProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessVideoGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 120;

    public function __construct(public readonly AiVideoGenerationJob $videoJob)
    {
    }

    public function handle(): void
    {
        $this->videoJob->update(['status' => 'processing']);

        try {
            $provider = AiProviderFactory::make();

            $videoPath = $provider->generateVideo(
                $this->videoJob->prompt,
                $this->videoJob->source_images ?? []
            );

            $this->videoJob->update([
                'status'            => 'completed',
                'result_video_path' => $videoPath,
                'provider'          => class_basename($provider),
            ]);

            // TODO: notify requester (vendor or marketer)
            // Notification::send($requester, new VideoReadyNotification($this->videoJob));
        } catch (Throwable $e) {
            Log::error('AI video generation failed', [
                'job_id' => $this->videoJob->id,
                'error'  => $e->getMessage(),
            ]);

            $this->videoJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
