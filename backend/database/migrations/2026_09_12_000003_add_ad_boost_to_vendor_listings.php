<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->boolean('is_ad_boosted')->default(false)->after('campaign_enabled');
            $table->timestamp('ad_boost_expires_at')->nullable()->after('is_ad_boosted');
            $table->index(['is_ad_boosted', 'ad_boost_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropIndex(['is_ad_boosted', 'ad_boost_expires_at']);
            $table->dropColumn(['is_ad_boosted', 'ad_boost_expires_at']);
        });
    }
};
