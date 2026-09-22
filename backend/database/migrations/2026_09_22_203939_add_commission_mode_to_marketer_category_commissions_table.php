<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketer_category_commissions', function (Blueprint $table) {
            $table->string('commission_mode', 20)->default('percentage')
                ->comment('fixed | percentage | both. When fixed/both, commission_flat_amount applies.')
                ->after('category_id');
            $table->unsignedBigInteger('commission_flat_amount')->nullable()
                ->comment('Flat commission amount (base currency unit), used when commission_mode is fixed or both.')
                ->after('commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketer_category_commissions', function (Blueprint $table) {
            $table->dropColumn(['commission_mode', 'commission_flat_amount']);
        });
    }
};
