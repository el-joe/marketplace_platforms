<?php

namespace App\Jobs;

use App\Models\FlashSaleSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmissionRejectedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $submissionId)
    {
    }

    public function handle(): void
    {
        $submission = FlashSaleSubmission::with(['vendor', 'flashSale'])->find($this->submissionId);
        if (!$submission) {
            return;
        }
        // TODO: send rejection notification to vendor with reason/code
    }
}
