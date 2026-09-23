<?php

namespace App\Console\Commands;

use App\Enums\FbnStorageFeeStatus;
use App\Models\Admin;
use App\Models\VendorListing;
use App\Notifications\Admin\OverstoredUnpaidProductFlagged;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Client feature request doc, section 6 ("تثبيت أول سعر للمنتج"): links the
 * price-history/first-price snapshot to the section-5 storage fees.
 *
 * Daily: any vendor listing that has been in FBN storage for over a year
 * AND whose total unpaid (pending/invoiced, i.e. not yet paid)
 * fbn_storage_fees exceed its locked-in first_price is flagged
 * disposable_by_admin so ops can act on it, with an admin notification.
 *
 * "Unpaid storage fees total" = SUM(fbn_storage_fees.total_fee) for that
 * listing's warehouse_inventories where status != paid (i.e. pending or
 * invoiced but not yet settled). fbn_storage_fees links to a listing via
 * warehouse_inventory_id -> warehouse_inventories.vendor_listing_id (see
 * GenerateFbnStorageFeesJob, which writes these rows).
 * "Storage duration" = time since the earliest warehouse_inventories row
 * for that listing was created (the same "stored_since" GenerateFbnStorageFeesJob
 * uses per inventory row).
 */
class FlagOverstoredUnpaidProducts extends Command
{
    protected $signature = 'products:flag-overstored-unpaid';

    protected $description = 'Flag vendor listings disposable_by_admin when unpaid storage fees exceed their locked-in first price after over a year in storage';

    public function handle(): int
    {
        $cutoff = now()->subDays(365);

        $rows = DB::table('vendor_listings')
            ->join('warehouse_inventories', 'warehouse_inventories.vendor_listing_id', '=', 'vendor_listings.id')
            ->join('fbn_storage_fees', 'fbn_storage_fees.warehouse_inventory_id', '=', 'warehouse_inventories.id')
            ->where('fbn_storage_fees.status', '!=', FbnStorageFeeStatus::Paid->value)
            ->whereNotNull('vendor_listings.first_price')
            ->where('vendor_listings.disposable_by_admin', false)
            ->whereNull('vendor_listings.deleted_at')
            ->groupBy('vendor_listings.id', 'vendor_listings.first_price')
            ->havingRaw('MIN(warehouse_inventories.created_at) <= ?', [$cutoff])
            ->havingRaw('SUM(fbn_storage_fees.total_fee) > vendor_listings.first_price')
            ->select([
                'vendor_listings.id as vendor_listing_id',
                DB::raw('SUM(fbn_storage_fees.total_fee) as unpaid_total'),
            ])
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No overstored unpaid products found.');

            return self::SUCCESS;
        }

        $admins = Admin::permission('vendors.edit')->get();
        $flagged = 0;

        foreach ($rows as $row) {
            $listing = VendorListing::find($row->vendor_listing_id);

            if (! $listing || $listing->disposable_by_admin) {
                continue;
            }

            $listing->forceFill(['disposable_by_admin' => true])->save();
            $flagged++;

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new OverstoredUnpaidProductFlagged($listing, (int) $row->unpaid_total));
            }
        }

        $this->info("Flagged {$flagged} overstored unpaid product(s) as disposable_by_admin.");

        return self::SUCCESS;
    }
}
