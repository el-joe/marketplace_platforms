<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->unsignedInteger('declared_weight_grams')->nullable()->after('units_stored');
            $table->unsignedInteger('volumetric_weight_grams')->nullable()->after('declared_weight_grams');
            $table->unsignedInteger('chargeable_weight_grams')->nullable()->after('volumetric_weight_grams');
            $table->unsignedInteger('free_days_applied')->nullable()->after('chargeable_weight_grams');
            $table->unsignedInteger('days_in_storage')->nullable()->after('free_days_applied');
            $table->boolean('within_free_period')->default(false)->after('days_in_storage');
        });
    }

    public function down(): void
    {
        Schema::table('fbn_storage_fees', function (Blueprint $table) {
            $table->dropColumn([
                'declared_weight_grams', 'volumetric_weight_grams', 'chargeable_weight_grams',
                'free_days_applied', 'days_in_storage', 'within_free_period',
            ]);
        });
    }
};
