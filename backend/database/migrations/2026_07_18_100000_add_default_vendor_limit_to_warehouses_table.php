<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->enum('default_limit_type', ['quantity', 'capacity'])->nullable()->after('used_capacity_m3');
            $table->integer('default_max_quantity')->nullable()->after('default_limit_type');
            $table->decimal('default_max_capacity_m3', 10, 2)->nullable()->after('default_max_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['default_limit_type', 'default_max_quantity', 'default_max_capacity_m3']);
        });
    }
};
