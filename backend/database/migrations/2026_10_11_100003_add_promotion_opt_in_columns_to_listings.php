<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The listing forms (partner + admin) and ListingService already write these four fields, but no migration
 * ever created them, so a seller's promotion opt-in (influencer / affiliate commission % and free-sample quota)
 * was silently dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['vendor_listings', 'admin_listings'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'influencer_commission_percentage')) {
                    $t->decimal('influencer_commission_percentage', 5, 2)->nullable();
                    $t->decimal('affiliate_commission_percentage', 5, 2)->nullable();
                    $t->unsignedInteger('influencer_sample_quota')->nullable();
                    $t->unsignedInteger('affiliate_sample_quota')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['vendor_listings', 'admin_listings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['influencer_commission_percentage', 'affiliate_commission_percentage', 'influencer_sample_quota', 'affiliate_sample_quota']);
            });
        }
    }
};
