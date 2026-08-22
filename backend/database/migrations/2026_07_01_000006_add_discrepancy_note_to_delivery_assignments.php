<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            // Agent-provided explanation when collected amount differs from expected.
            $table->text('discrepancy_note')->nullable()->after('cod_amount_collected_cents');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn('discrepancy_note');
        });
    }
};
