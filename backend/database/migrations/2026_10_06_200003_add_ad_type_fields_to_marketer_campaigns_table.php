<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->json('selected_ad_types')->nullable()->after('notes');
            $table->text('vendor_ad_notes')->nullable()->after('selected_ad_types');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropColumn(['selected_ad_types', 'vendor_ad_notes']);
        });
    }
};
