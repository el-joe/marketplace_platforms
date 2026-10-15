<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R2): triggers and attribution for the classified and travel verticals.
 *
 *  - classified_listings: sold_price / sold_at / sold_via_campaign_id (captured when the seller marks the listing sold).
 *  - classified_inquiries: marketer_id / marketer_campaign_id / referral_code (who referred the inquiry).
 *  - travel_bookings: marketer_campaign_id / marketer_id / referral_code / completed_at.
 *
 * All additive and nullable; foreign keys set to NULL on delete so history survives campaign / marketer removal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('classified_listings', 'sold_price')) {
            Schema::table('classified_listings', fn (Blueprint $t) => $t->decimal('sold_price', 19, 4)->nullable()->comment('Final sale price entered when marked sold'));
        }
        if (! Schema::hasColumn('classified_listings', 'sold_at')) {
            Schema::table('classified_listings', fn (Blueprint $t) => $t->timestamp('sold_at')->nullable());
        }
        if (! Schema::hasColumn('classified_listings', 'sold_via_campaign_id')) {
            Schema::table('classified_listings', fn (Blueprint $t) => $t->foreignUuid('sold_via_campaign_id')->nullable()->constrained('marketer_campaigns')->nullOnDelete());
        }

        $this->addAttribution('classified_inquiries');
        $this->addAttribution('travel_bookings');

        if (! Schema::hasColumn('travel_bookings', 'completed_at')) {
            Schema::table('travel_bookings', fn (Blueprint $t) => $t->timestamp('completed_at')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['classified_inquiries', 'travel_bookings'] as $tableName) {
            foreach (['marketer_campaign_id', 'marketer_id'] as $fk) {
                if (Schema::hasColumn($tableName, $fk)) {
                    Schema::table($tableName, fn (Blueprint $t) => $t->dropConstrainedForeignId($fk));
                }
            }
            if (Schema::hasColumn($tableName, 'referral_code')) {
                Schema::table($tableName, fn (Blueprint $t) => $t->dropColumn('referral_code'));
            }
        }

        if (Schema::hasColumn('travel_bookings', 'completed_at')) {
            Schema::table('travel_bookings', fn (Blueprint $t) => $t->dropColumn('completed_at'));
        }
        if (Schema::hasColumn('classified_listings', 'sold_via_campaign_id')) {
            Schema::table('classified_listings', fn (Blueprint $t) => $t->dropConstrainedForeignId('sold_via_campaign_id'));
        }
        foreach (['sold_price', 'sold_at'] as $column) {
            if (Schema::hasColumn('classified_listings', $column)) {
                Schema::table('classified_listings', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }

    private function addAttribution(string $table): void
    {
        if (! Schema::hasColumn($table, 'marketer_id')) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignUuid('marketer_id')->nullable()->constrained('marketers')->nullOnDelete());
        }
        if (! Schema::hasColumn($table, 'marketer_campaign_id')) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignUuid('marketer_campaign_id')->nullable()->constrained('marketer_campaigns')->nullOnDelete());
        }
        if (! Schema::hasColumn($table, 'referral_code')) {
            Schema::table($table, fn (Blueprint $t) => $t->string('referral_code', 50)->nullable()->index());
        }
    }
};
