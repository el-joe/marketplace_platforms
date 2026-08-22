<?php

namespace App\Jobs;

use App\Models\ClassifiedInquiry;
use App\Notifications\Customer\ClassifiedInquiryReceived;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifySellerNewInquiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly ClassifiedInquiry $inquiry) {}

    public function handle(): void
    {
        $this->inquiry->loadMissing('listing.seller');

        $seller = $this->inquiry->listing?->seller;

        if (! $seller) {
            Log::warning('NotifySellerNewInquiryJob: seller not found for inquiry', [
                'inquiry_id' => $this->inquiry->id,
            ]);
            return;
        }

        $seller->notify(new ClassifiedInquiryReceived($this->inquiry));
    }
}
