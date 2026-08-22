<?php

namespace App\Jobs;

use App\Models\ClassifiedListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminNewClassifiedListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ClassifiedListing $listing) {}

    public function handle(): void
    {
        // Notify admins that a new classified listing is awaiting review.
        // Wire up to the platform's admin notification channel (email/push/in-app)
        // once the notification infrastructure for classifieds is defined.
    }
}
