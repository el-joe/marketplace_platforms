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
        Schema::create('open_market_category_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id')->nullable()
                ->comment('Null = default rate for this classified category, applied to all marketers.');
            $table->uuid('classified_category_id')->nullable()
                ->comment('Null = default rate applied across all classified categories for this marketer.');
            $table->string('commission_mode', 20)->default('percentage')
                ->comment('fixed | percentage | both.');
            $table->decimal('commission_rate', 5, 2)->default(0)
                ->comment('Percentage, e.g. 8.00 = 8%.');
            $table->unsignedBigInteger('commission_flat_amount')->nullable()
                ->comment('Flat commission amount (base currency unit), used when commission_mode is fixed or both.');
            $table->uuid('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['marketer_id', 'classified_category_id'], 'omcc_marketer_category_unique');

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->cascadeOnDelete();
            $table->foreign('updated_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_market_category_commissions');
    }
};
