<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_ad_creatives', function (Blueprint $t) {
            $t->char('vendor_id', 36)->nullable()->change();
            $t->char('marketer_id', 36)->nullable()->after('vendor_id');
            $t->string('subtitle_en', 255)->nullable()->after('title_ar');
            $t->string('subtitle_ar', 255)->nullable()->after('subtitle_en');
            $t->string('destination_type', 30)->change();
            $t->string('referral_code', 50)->nullable()->after('destination_reference_id')
                ->comment('Snapshot for destination_type=campaign (marketer referral)');
            $t->unsignedInteger('version')->default(1)->after('paid_ad_booking_id');

            $t->foreign('paid_ad_booking_id')->references('id')->on('paid_ad_bookings')->cascadeOnDelete();
            $t->foreign('marketer_id')->references('id')->on('marketers')->restrictOnDelete();
            $t->index(['paid_ad_booking_id', 'is_current', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('paid_ad_creatives', function (Blueprint $t) {
            $t->dropForeign(['paid_ad_booking_id']);
            $t->dropForeign(['marketer_id']);
            $t->dropIndex(['paid_ad_booking_id', 'is_current', 'status']);

            $t->dropColumn(['marketer_id', 'subtitle_en', 'subtitle_ar', 'referral_code', 'version']);
            $t->string('destination_type', 20)->change();
            $t->char('vendor_id', 36)->nullable(false)->change();
        });
    }
};
