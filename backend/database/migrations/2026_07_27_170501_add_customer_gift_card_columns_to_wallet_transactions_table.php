<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Widens wallet_transactions to also serve the customer-wallet gift-card
     * redemption flow (App\Models\WalletTransaction) alongside the existing
     * legacy Wallet/WalletService rows. 'type' is widened from an enum of
     * credit/debit to a free-text string; legacy rows keep writing
     * credit/debit into it while new rows use values like 'gift_card_redeem'
     * and rely on the new 'direction' column for credit/debit semantics.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE wallet_transactions MODIFY wallet_id CHAR(36) NULL");
        DB::statement("ALTER TABLE wallet_transactions MODIFY type VARCHAR(50) NOT NULL");

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('direction', ['credit', 'debit'])->nullable()->after('type');

            $table->uuid('customer_id')->nullable()->after('wallet_id');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->char('currency_code', 3)->nullable()->after('balance_after');

            $table->string('reference_type')->nullable()->after('currency_code');
            $table->uuid('reference_id')->nullable()->after('reference_type');

            $table->string('note')->nullable()->after('description');

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'direction', 'currency_code', 'reference_type', 'reference_id', 'note']);
        });

        DB::statement("ALTER TABLE wallet_transactions MODIFY type ENUM('credit', 'debit') NOT NULL");
        DB::statement("ALTER TABLE wallet_transactions MODIFY wallet_id CHAR(36) NOT NULL");
    }
};
