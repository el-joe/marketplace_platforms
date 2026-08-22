<?php

namespace App\Jobs;

use App\Enums\CarrierClaimStatus;
use App\Models\CarrierClaim;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreditVendorCompensationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly CarrierClaim $claim) {}

    public function handle(WalletService $walletService): void
    {
        $claim = $this->claim->fresh(['shipment.subOrder.vendor']);

        if (! $claim || $claim->status !== CarrierClaimStatus::Approved) {
            return;
        }

        if (! $claim->compensated_amount) {
            return;
        }

        $vendor = $claim->shipment?->subOrder?->vendor;

        if (! $vendor) {
            Log::warning("CreditVendorCompensationJob: could not resolve vendor for claim {$claim->id}");
            return;
        }

        $wallet = $walletService->getOrCreateWallet(
            'vendor',
            $vendor->id,
            'EGP' // TODO: use shipment currency when multi-currency is wired
        );

        $walletService->credit(
            wallet:              $wallet,
            amountCents:         $claim->compensated_amount,
            sourceType:          'carrier_claim',
            sourceId:            $claim->id,
            description:         "Carrier claim compensation #{$claim->claim_number}",
            performedByAdminId:  $claim->resolved_by_admin_id,
        );

        $claim->update(['status' => 'compensated']);
    }
}
