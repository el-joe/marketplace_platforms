<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->foreignUuid('cod_settlement_id')
                ->nullable()
                ->after('cod_amount_collected_cents')
                ->constrained('delivery_agent_cod_settlements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropForeign(['cod_settlement_id']);
            $table->dropColumn('cod_settlement_id');
        });
    }
};
