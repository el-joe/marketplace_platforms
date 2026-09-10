<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_ad_bookings', function (Blueprint $t) {
            $t->enum('advertiser_type', ['vendor', 'marketer'])->default('vendor')->after('paid_ad_slot_id');
            $t->char('vendor_id', 36)->nullable()->change();
            $t->char('marketer_id', 36)->nullable()->after('vendor_id');

            $t->unsignedInteger('pricing_units')->default(0)->after('pricing_model')
                ->comment('days / weeks / months for fixed models; 0 for cpm/cpc');
            $t->bigInteger('unit_rate')->default(0)->after('pricing_units')->comment('Snapshot of slot.base_rate');
            $t->bigInteger('quoted_amount')->default(0)->after('unit_rate')->comment('Fixed total before tax');
            $t->bigInteger('tax_amount')->default(0)->after('quoted_amount');
            $t->bigInteger('budget_amount')->nullable()->after('tax_amount')->comment('CPM/CPC spend cap');

            $t->enum('payment_method', ['wallet', 'payout_deduction', 'offline'])->nullable()->after('payment_status');
            $t->timestamp('payment_due_at')->nullable()->after('paid_at');

            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('paused_at')->nullable();
            $t->char('rejected_by_admin_id', 36)->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->enum('cancelled_by', ['vendor', 'marketer', 'admin', 'system'])->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->char('created_by_admin_id', 36)->nullable()->comment('Set when admin books on behalf of an advertiser');
        });

        // Legacy status values -> new vocabulary (must run before the FK/index block below,
        // and before any CHECK-like assumption that status is already in the new set).
        DB::table('paid_ad_bookings')->where('status', 'pending')->update(['status' => 'pending_review']);
        DB::table('paid_ad_bookings')->where('status', 'ended')->update(['status' => 'completed']);
        DB::table('paid_ad_bookings')->where('payment_status', 'invoiced')->update(['payment_status' => 'unpaid']);

        Schema::table('paid_ad_bookings', function (Blueprint $t) {
            $t->foreign('paid_ad_slot_id')->references('id')->on('paid_ad_slots')->restrictOnDelete();
            $t->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
            $t->foreign('marketer_id')->references('id')->on('marketers')->restrictOnDelete();
            $t->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $t->index(['paid_ad_slot_id', 'status', 'booked_from', 'booked_until'], 'pab_slot_status_dates_idx');
            $t->index(['advertiser_type', 'vendor_id', 'status']);
            $t->index(['advertiser_type', 'marketer_id', 'status']);
            $t->index(['status', 'payment_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('paid_ad_bookings', function (Blueprint $t) {
            $t->dropForeign(['paid_ad_slot_id']);
            $t->dropForeign(['vendor_id']);
            $t->dropForeign(['marketer_id']);
            $t->dropForeign(['country_id']);
            $t->dropIndex('pab_slot_status_dates_idx');
            $t->dropIndex(['advertiser_type', 'vendor_id', 'status']);
            $t->dropIndex(['advertiser_type', 'marketer_id', 'status']);
            $t->dropIndex(['status', 'payment_due_at']);
        });

        DB::table('paid_ad_bookings')->where('status', 'pending_review')->update(['status' => 'pending']);
        DB::table('paid_ad_bookings')->where('status', 'completed')->update(['status' => 'ended']);
        DB::table('paid_ad_bookings')->where('payment_status', 'unpaid')->update(['payment_status' => 'invoiced']);

        Schema::table('paid_ad_bookings', function (Blueprint $t) {
            $t->dropColumn([
                'created_by_admin_id',
                'cancellation_reason',
                'cancelled_by',
                'cancelled_at',
                'rejected_at',
                'rejected_by_admin_id',
                'paused_at',
                'completed_at',
                'started_at',
                'submitted_at',
                'payment_due_at',
                'payment_method',
                'budget_amount',
                'tax_amount',
                'quoted_amount',
                'unit_rate',
                'pricing_units',
                'marketer_id',
                'advertiser_type',
            ]);

            $t->char('vendor_id', 36)->nullable(false)->change();
        });
    }
};
