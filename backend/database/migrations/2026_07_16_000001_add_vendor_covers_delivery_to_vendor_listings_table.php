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
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->tinyInteger('vendor_covers_delivery')
                ->default(0)
                ->comment('Vendor opts in to cover remaining delivery cost after admin subsidy — customer sees Free Delivery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropColumn('vendor_covers_delivery');
        });
    }
};
