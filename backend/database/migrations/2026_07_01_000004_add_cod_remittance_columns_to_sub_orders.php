<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->boolean('cod_remittance_confirmed')
                ->default(false)
                ->comment('For COD orders: true only after the delivery agent\'s COD settlement covering this order has been marked settled. Vendor payout is blocked until this is true.')
                ->after('vendor_payout');
            $table->timestamp('cod_remittance_confirmed_at')->nullable()->after('cod_remittance_confirmed');
            $table->foreignUuid('cod_settlement_id')->nullable()
                ->comment('The settlement that confirmed cash receipt for this sub_order.')
                ->after('cod_remittance_confirmed_at')
                ->constrained('delivery_agent_cod_settlements')
                ->nullOnDelete();
        });

        // Backfill: non-COD sub_orders are always considered remitted (gate not applicable).
        // COD sub_orders remain false (pending manual settlement confirmation).
        DB::statement("
            UPDATE sub_orders
            SET cod_remittance_confirmed = 1
            WHERE order_id IN (
                SELECT id FROM orders WHERE payment_method != 'cod'
            )
        ");
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropForeign(['cod_settlement_id']);
            $table->dropColumn(['cod_remittance_confirmed', 'cod_remittance_confirmed_at', 'cod_settlement_id']);
        });
    }
};
