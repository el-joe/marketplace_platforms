<?php

namespace App\Services\Fbn;

use App\Enums\FbnStorageFeeStatus;
use App\Exceptions\StorageFeeStatusChangeException;
use App\Models\Admin;
use App\Models\FbnStorageFee;
use App\Models\Payout;
use App\Services\ActivityLoggerService;
use Illuminate\Support\Facades\DB;

/**
 * Admin status workflow for FBN storage fees (client item #9):
 *
 *   pending --Mark Invoiced--> invoiced --Mark Paid--> paid
 *   paid --revert--> invoiced --revert--> pending   (permission-gated, reason required)
 *
 * Every change writes an activity-log entry (actor, before/after, reason).
 *
 * The statuses carry no ledger, wallet or invoice records of their own — the
 * only side effect lives in GenerateVendorPayoutsJob, which deducts PENDING
 * fees from a vendor payout and marks them invoiced. Reverting such a fee to
 * pending would make the next payout deduct it a second time, so it is refused.
 */
class StorageFeeStatusService
{
    public const REVERT_PERMISSION = 'fbn_storage_fees.revert_status';

    public function __construct(private readonly ActivityLoggerService $activityLogger) {}

    public function advance(FbnStorageFee $fee, FbnStorageFeeStatus $target, Admin $admin): FbnStorageFee
    {
        return DB::transaction(function () use ($fee, $target, $admin) {
            $fee = FbnStorageFee::whereKey($fee->getKey())->lockForUpdate()->firstOrFail();
            $from = $fee->status;

            $allowed = ($from === FbnStorageFeeStatus::Pending && $target === FbnStorageFeeStatus::Invoiced)
                || ($from === FbnStorageFeeStatus::Invoiced && $target === FbnStorageFeeStatus::Paid);

            if (! $allowed) {
                throw StorageFeeStatusChangeException::invalidTransition();
            }

            $fee->status = $target;
            if ($target === FbnStorageFeeStatus::Invoiced) {
                $fee->invoiced_at = now();
            } else {
                $fee->paid_at = now();
            }
            $fee->save();

            $this->log($fee, $admin, $from, $target, null);

            return $fee;
        });
    }

    public function revert(FbnStorageFee $fee, string $reason, Admin $admin): FbnStorageFee
    {
        return DB::transaction(function () use ($fee, $reason, $admin) {
            $fee = FbnStorageFee::whereKey($fee->getKey())->lockForUpdate()->firstOrFail();
            $from = $fee->status;

            $target = match ($from) {
                FbnStorageFeeStatus::Paid => FbnStorageFeeStatus::Invoiced,
                FbnStorageFeeStatus::Invoiced => FbnStorageFeeStatus::Pending,
                default => throw StorageFeeStatusChangeException::invalidTransition(),
            };

            if ($target === FbnStorageFeeStatus::Pending) {
                $this->assertNotDeductedInPayout($fee);
                $fee->invoiced_at = null;
            } else {
                $fee->paid_at = null;
            }

            $fee->status = $target;
            $fee->save();

            $this->log($fee, $admin, $from, $target, $reason);

            return $fee;
        });
    }

    /**
     * A fee invoiced by a payout run carries payout_id. Fees invoiced before
     * that link existed carry neither payout_id nor invoiced_at; for those,
     * any later payout of the same vendor/currency that deducted storage fees
     * may have included it, so the revert is refused to be safe.
     */
    private function assertNotDeductedInPayout(FbnStorageFee $fee): void
    {
        if ($fee->payout_id !== null) {
            $payoutNumber = Payout::whereKey($fee->payout_id)->value('payout_number');

            throw StorageFeeStatusChangeException::deductedInPayout($payoutNumber ?? '#'.$fee->payout_id);
        }

        if ($fee->invoiced_at !== null) {
            return;
        }

        $possiblePayout = Payout::query()
            ->where('vendor_id', $fee->vendor_id)
            ->where('currency', $fee->currency)
            ->where('storage_fees', '>', 0)
            ->where('created_at', '>=', $fee->created_at)
            ->orderBy('created_at')
            ->value('payout_number');

        if ($possiblePayout !== null) {
            throw StorageFeeStatusChangeException::deductedInPayout($possiblePayout);
        }
    }

    private function log(FbnStorageFee $fee, Admin $admin, FbnStorageFeeStatus $from, FbnStorageFeeStatus $to, ?string $reason): void
    {
        $isRevert = $reason !== null;
        $fee->loadMissing('vendor:id,store_name');

        $this->activityLogger->log(
            description: __($isRevert ? 'admin.fbn_section.activity_reverted' : 'admin.fbn_section.activity_status_changed', [
                'vendor' => $fee->vendor?->store_name ?? '—',
                'month' => $fee->monthLabel(),
                'from' => $from->label(),
                'to' => $to->label(),
            ]),
            subject: $fee,
            causer: $admin,
            properties: [
                'subject_display_name' => ($fee->vendor?->store_name ?? '—').' · '.$fee->monthLabel(),
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'vendor_id' => $fee->vendor_id,
                'month' => $fee->month?->toDateString(),
                'total_fee' => (float) $fee->total_fee,
                'currency' => $fee->currency,
            ],
            logName: 'fbn_fees',
            event: $isRevert ? 'storage_fee_status_reverted' : 'storage_fee_'.$to->value,
        );
    }
}
