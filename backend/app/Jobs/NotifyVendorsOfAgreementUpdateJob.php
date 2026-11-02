<?php

namespace App\Jobs;

use App\Mail\SellerAgreementUpdatedMail;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Notifications\Vendor\SellerAgreementUpdated;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells every vendor that has not signed this Seller Agreement version about it, in-app and by email.
 */
class NotifyVendorsOfAgreementUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $templateId) {}

    public function handle(SellerAgreementService $agreements): void
    {
        $template = ClassifiedContractTemplate::find($this->templateId);

        if (! $template || ! $template->is_published) {
            return;
        }

        $agreements->vendorsToNotify($template)
            ->with('vendorAdmins')
            ->chunkById(100, function ($vendors) use ($template) {
                /** @var Vendor $vendor */
                foreach ($vendors as $vendor) {
                    $vendor->vendorAdmins->each(fn (VendorAdmin $vendorAdmin) => $vendorAdmin->notify(
                        new SellerAgreementUpdated($template, $vendorAdmin->id),
                    ));

                    try {
                        Mail::to($vendor->contact_email ?: $vendor->email)->queue(new SellerAgreementUpdatedMail($template, (string) $vendor->name));
                    } catch (\Throwable $e) {
                        Log::warning('Seller agreement update email failed: '.$e->getMessage(), ['vendor_id' => $vendor->id]);
                    }
                }
            });
    }
}
