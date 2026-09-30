<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table) {
            $table->foreignUuid('bookable_unit_id')->nullable()->after('travel_package_id')
                ->constrained('bookable_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table) {
            $table->dropForeign(['bookable_unit_id']);
            $table->dropColumn('bookable_unit_id');
        });
    }
};
