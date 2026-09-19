<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * total_charged was overloaded: for fixed bookings it held the subscription
 * fee, for CPM/CPC the usage spend. total_charged now means usage spend only;
 * the fixed fee (ex-tax) lives in subscription_charged.
 */
return new class extends Migration
{
    private const FIXED_MODELS = ['fixed_daily', 'fixed_weekly', 'fixed_monthly'];

    private const PAID_STATUSES = ['paid', 'reserved', 'partially_refunded'];

    public function up(): void
    {
        if (! Schema::hasColumn('paid_ad_bookings', 'subscription_charged')) {
            Schema::table('paid_ad_bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('subscription_charged')->default(0)->after('total_charged');
            });
        }

        // Idempotent: migrated rows have total_charged = 0 and are not selected again.
        $rows = DB::table('paid_ad_bookings')
            ->whereIn('pricing_model', self::FIXED_MODELS)
            ->where('total_charged', '>', 0)
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->get(['id', 'booking_reference', 'total_charged']);

        foreach ($rows as $row) {
            $ledger = DB::table('paid_ad_charges')
                ->where('paid_ad_booking_id', $row->id)
                ->where('type', 'fixed')
                ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount - tax_amount), 0) as ex_tax')
                ->first();

            if ((int) $ledger->c > 0 && (int) $ledger->ex_tax !== (int) $row->total_charged) {
                Log::warning('paid_ad_bookings backfill skipped: total_charged disagrees with fixed ledger', [
                    'booking_id' => $row->id,
                    'booking_reference' => $row->booking_reference,
                    'total_charged' => (int) $row->total_charged,
                    'ledger_ex_tax' => (int) $ledger->ex_tax,
                ]);

                continue;
            }

            DB::table('paid_ad_bookings')->where('id', $row->id)->update([
                'subscription_charged' => $row->total_charged,
                'total_charged' => 0,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('paid_ad_bookings', 'subscription_charged')) {
            return;
        }

        DB::table('paid_ad_bookings')
            ->whereIn('pricing_model', self::FIXED_MODELS)
            ->where('subscription_charged', '>', 0)
            ->update(['total_charged' => DB::raw('subscription_charged')]);

        Schema::table('paid_ad_bookings', function (Blueprint $table) {
            $table->dropColumn('subscription_charged');
        });
    }
};
