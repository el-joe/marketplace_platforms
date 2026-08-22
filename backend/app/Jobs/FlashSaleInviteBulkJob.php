<?php

namespace App\Jobs;

use App\Models\FlashSaleVendorInvitition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FlashSaleInviteBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $flashSaleId,
        public readonly array $vendorIds
    ) {
    }

    public function handle(): void
    {
        FlashSaleVendorInvitition::whereIn('vendor_id', $this->vendorIds)
            ->where('flash_sale_id', $this->flashSaleId)
            ->whereNull('notified_at')
            ->each(function (FlashSaleVendorInvitition $invitation) {
                // TODO: dispatch per-vendor notification (email/push)
                $invitation->update(['notified_at' => now()]);
            });
    }
}
