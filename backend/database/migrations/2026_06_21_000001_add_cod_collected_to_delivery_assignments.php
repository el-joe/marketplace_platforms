<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->bigInteger('cod_amount_collected_cents')->nullable()->after('delivery_otp');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn('cod_amount_collected_cents');
        });
    }
};
