<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agent_cod_settlements', function (Blueprint $table) {
            $table->boolean('has_collection_discrepancy')->default(false)->after('notes');
            $table->text('discrepancy_notes')->nullable()->after('has_collection_discrepancy');
            $table->bigInteger('discrepancy_amount_cents')->default(0)->after('discrepancy_notes');
            $table->enum('discrepancy_resolution', [
                'pending',
                'deducted_from_earnings',
                'written_off',
                'vendor_chargeback',
            ])->nullable()->after('discrepancy_amount_cents');

            $table->index('has_collection_discrepancy');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agent_cod_settlements', function (Blueprint $table) {
            $table->dropIndex(['has_collection_discrepancy']);
            $table->dropColumn([
                'has_collection_discrepancy',
                'discrepancy_notes',
                'discrepancy_amount_cents',
                'discrepancy_resolution',
            ]);
        });
    }
};
