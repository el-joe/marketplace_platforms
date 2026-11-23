<?php

namespace App\Services\Fbn;

use App\Enums\FbnStorageFeeStatus;
use App\Models\Admin;
use App\Models\FbnStorageFee;
use App\Services\ActivityLoggerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Re-prices PENDING FBN storage fees against the current free-period rules
 * (client item #11). Invoiced and paid fees are never touched.
 *
 * Nothing here runs automatically: after a rule change the admin sees
 * preview() (how many pending fees would change and by how much) and must
 * confirm before recalculate() writes anything. Without confirmation the
 * pending fees keep their amounts until that month is generated again.
 *
 * Each fee keeps its own snapshot (units, rate, chargeable weight, storage
 * start, days in storage); only the free days, free-period flag and total
 * are recomputed. The physical volume comes from the listing's dimensions,
 * the same source the monthly run uses.
 */
class StorageFeeRecalculationService
{
    public function __construct(
        private readonly StorageFeeCalculator $calculator,
        private readonly ActivityLoggerService $activityLogger,
    ) {}

    /**
     * @return array{count: int, totals: array<string, array{current: float, recalculated: float}>}
     */
    public function preview(): array
    {
        $summary = ['count' => 0, 'totals' => []];

        $this->pendingFeesQuery()->chunkById(500, function ($fees) use (&$summary) {
            foreach ($fees as $fee) {
                $changes = $this->changesFor($fee);
                if ($changes !== null) {
                    $this->addToSummary($summary, $fee, $changes);
                }
            }
        });

        return $summary;
    }

    /**
     * @return array{count: int, totals: array<string, array{current: float, recalculated: float}>}
     */
    public function recalculate(Admin $admin): array
    {
        $summary = DB::transaction(function () {
            $summary = ['count' => 0, 'totals' => []];

            $this->pendingFeesQuery()->lockForUpdate()->chunkById(500, function ($fees) use (&$summary) {
                foreach ($fees as $fee) {
                    $changes = $this->changesFor($fee);
                    if ($changes === null) {
                        continue;
                    }

                    $this->addToSummary($summary, $fee, $changes);
                    $fee->update($changes);
                }
            });

            return $summary;
        });

        $this->activityLogger->log(
            description: __('admin.fbn_section.activity_recalculated', ['count' => $summary['count']]),
            causer: $admin,
            properties: $summary,
            logName: 'fbn_fees',
            event: 'storage_fees_recalculated',
        );

        return $summary;
    }

    /**
     * @return Builder<FbnStorageFee>
     */
    private function pendingFeesQuery(): Builder
    {
        return FbnStorageFee::query()
            ->where('status', FbnStorageFeeStatus::Pending)
            ->whereNotNull('stored_since')
            ->with('warehouseInventory.vendorListing:id,declared_length_cm,declared_width_cm,declared_height_cm');
    }

    /**
     * @return array{free_days_applied: int, within_free_period: bool, total_fee: float}|null null when nothing changes
     */
    private function changesFor(FbnStorageFee $fee): ?array
    {
        $listing = $fee->warehouseInventory?->vendorListing;
        if ($listing === null) {
            return null;
        }

        $charge = $this->calculator->charge(
            (int) $fee->chargeable_weight_grams,
            $fee->stored_since,
            (int) $fee->days_in_storage,
            $this->calculator->volumeM3((float) $listing->declared_length_cm, (float) $listing->declared_width_cm, (float) $listing->declared_height_cm),
            (float) $fee->rate_per_unit,
            (int) $fee->units_stored,
        );

        $unchanged = $charge['free_days_applied'] === (int) $fee->free_days_applied
            && $charge['within_free_period'] === (bool) $fee->within_free_period
            && abs($charge['total_fee'] - (float) $fee->total_fee) < 0.005;

        return $unchanged ? null : $charge;
    }

    /**
     * @param  array{count: int, totals: array<string, array{current: float, recalculated: float}>}  $summary
     * @param  array{total_fee: float}  $changes
     */
    private function addToSummary(array &$summary, FbnStorageFee $fee, array $changes): void
    {
        $summary['count']++;
        $summary['totals'][$fee->currency] ??= ['current' => 0.0, 'recalculated' => 0.0];
        $summary['totals'][$fee->currency]['current'] = round($summary['totals'][$fee->currency]['current'] + (float) $fee->total_fee, 2);
        $summary['totals'][$fee->currency]['recalculated'] = round($summary['totals'][$fee->currency]['recalculated'] + $changes['total_fee'], 2);
    }
}
