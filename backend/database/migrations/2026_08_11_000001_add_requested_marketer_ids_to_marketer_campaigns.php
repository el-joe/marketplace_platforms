<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->json('requested_marketer_vendor_ids')->nullable()->after('marketer_commission_amount')
                ->comment('Marketer vendor IDs selected by the vendor at campaign creation, before admin review');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_campaigns', function (Blueprint $table) {
            $table->dropColumn('requested_marketer_vendor_ids');
        });
    }
};
