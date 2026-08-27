<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('gift_card_batches', 'is_purchasable')) {
                $table->boolean('is_purchasable')
                    ->default(false)
                    ->after('quantity')
                    ->comment('True = listed on customer storefront for purchase');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table) {
            if (Schema::hasColumn('gift_card_batches', 'is_purchasable')) {
                $table->dropColumn('is_purchasable');
            }
        });
    }
};
