<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->unsignedInteger('per_delivery_fee')->nullable()->after('total_deliveries');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropColumn('per_delivery_fee');
        });
    }
};
