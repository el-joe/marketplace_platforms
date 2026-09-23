<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client feature request doc, section 6 ("تثبيت أول سعر للمنتج / Price History").
 *
 * first_price / first_price_locked_at: the listing's first-ever price,
 * locked at creation (incl. drafts) and never touched again afterwards,
 * not even by admin — see VendorListingObserver::created().
 *
 * disposable_by_admin: added here rather than in the earlier section-5
 * storage-fees pass, per the plan's cross-link between the two features —
 * it's flagged true by FlagOverstoredUnpaidProducts once a listing's
 * unpaid storage fees exceed this same first_price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->bigInteger('first_price')->nullable()->after('price');
            $table->timestamp('first_price_locked_at')->nullable()->after('first_price');
            $table->boolean('disposable_by_admin')->default(false)->after('first_price_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropColumn(['first_price', 'first_price_locked_at', 'disposable_by_admin']);
        });
    }
};
