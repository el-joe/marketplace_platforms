<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->bigInteger('ad_price')->unsigned()->default(0)
                ->after('earnings_currency')
                ->comment('Display ad price shown on the marketer public profile. BIGINT base-currency. No /100.');
            $table->char('ad_price_currency', 3)->nullable()->after('ad_price');
            $table->boolean('can_self_edit_ad_price')->default(false)->after('ad_price_currency')
                ->comment('When true, the marketer is allowed to edit their own ad_price from the marketer panel.');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropColumn(['ad_price', 'ad_price_currency', 'can_self_edit_ad_price']);
        });
    }
};
