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
        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_amount_to_use')
                ->default(0)
                ->after('discount')
                ->comment('BIGINT base-currency. How much of the wallet balance the customer wants to apply at checkout. Deducted from estimated_total display. No /100.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('wallet_amount_to_use');
        });
    }
};
