<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('remaining_balance')
                ->default(0)
                ->after('amount')
                ->comment('BIGINT base-currency. Initialized = amount. Decremented on redemption.');

            $table->enum('source', ['batch', 'purchased', 'gifted', 'refund_credit'])
                ->default('batch')
                ->after('gift_card_batch_id');

            $table->char('issued_to_customer_id', 36)
                ->nullable()
                ->after('redeemed_by_customer_id')
                ->comment('If set, only this customer can redeem this card.');
        });

        DB::statement("UPDATE gift_cards SET remaining_balance = amount WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn(['issued_to_customer_id', 'source', 'remaining_balance']);
        });
    }
};
