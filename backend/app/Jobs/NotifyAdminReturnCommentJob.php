<?php

namespace App\Jobs;

use App\Models\ReturnRequestMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminReturnCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ReturnRequestMessage $message) {}

    public function handle(): void
    {
        // Admin notification logic (email/dashboard alert) goes here.
    }
}
