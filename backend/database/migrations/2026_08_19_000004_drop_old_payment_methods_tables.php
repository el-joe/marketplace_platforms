<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_transactions', 'payment_method_id')) {
                try {
                    $table->dropForeign(['payment_method_id']);
                } catch (\Throwable) {
                    // FK may already be dropped — proceed
                }
                $table->dropColumn('payment_method_id');
            }
        });

        Schema::dropIfExists('country_payment_methods');
        Schema::dropIfExists('payment_methods');   // customer saved cards — no longer used
    }

    public function down(): void
    {
        // Non-reversible. Restore from backup if needed.
    }
};
