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
        Schema::table('bookable_units', function (Blueprint $table) {
            $table->uuid('travel_package_id')->nullable()->after('travel_agency_id');
            $table->foreign('travel_package_id')->references('id')->on('travel_packages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookable_units', function (Blueprint $table) {
            $table->dropForeign(['travel_package_id']);
            $table->dropColumn('travel_package_id');
        });
    }
};
