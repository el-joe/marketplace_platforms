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
        Schema::table('country_categories', function (Blueprint $table) {
            $table->decimal('commission_fbp_pct', 5, 2)->nullable()->after('commission_rate')
                ->comment('Country-level FBP commission % override. NULL = inherit category rate.');
            $table->bigInteger('commission_fbp_fixed')->nullable()->after('commission_fbp_pct')
                ->comment('Country-level FBP fixed fee per unit override (base currency units). NULL = inherit.');
            $table->decimal('commission_fbn_pct', 5, 2)->nullable()->after('commission_fbp_fixed')
                ->comment('Country-level FBN commission % override. NULL = inherit category rate.');
            $table->bigInteger('commission_fbn_fixed')->nullable()->after('commission_fbn_pct')
                ->comment('Country-level FBN fixed fee per unit override (base currency units). NULL = inherit.');

            $table->dropColumn('commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country_categories', function (Blueprint $table) {
            $table->dropColumn(['commission_fbp_pct', 'commission_fbp_fixed', 'commission_fbn_pct', 'commission_fbn_fixed']);
            $table->decimal('commission_rate', 5, 2)->nullable()->after('is_available');
        });
    }
};
