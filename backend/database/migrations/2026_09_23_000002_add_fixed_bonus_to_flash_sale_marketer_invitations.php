<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_sale_marketer_invitations', function (Blueprint $table) {
            $table->string('extra_commission_mode', 20)->default('percentage')->after('extra_commission_rate');
            $table->unsignedBigInteger('extra_commission_flat_amount')->nullable()->after('extra_commission_mode');
        });
    }

    public function down(): void
    {
        Schema::table('flash_sale_marketer_invitations', function (Blueprint $table) {
            $table->dropColumn(['extra_commission_mode', 'extra_commission_flat_amount']);
        });
    }
};
