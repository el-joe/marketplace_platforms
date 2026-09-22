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
        Schema::create('exclusive_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id')
                ->comment('The marketer this open-market exclusive contract is granted to.');
            $table->uuid('classified_category_id')->nullable()
                ->comment('Null = applies to all classified categories.');
            $table->uuid('classified_listing_id')->nullable()
                ->comment('Null = applies to the whole category rather than one specific listing.');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('pending')
                ->comment('pending | active | expired | revoked');
            $table->string('contract_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable()
                ->comment('Admin who created this exclusive contract.');
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->cascadeOnDelete();
            $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();

            $table->index(['classified_listing_id', 'status'], 'exclusive_contracts_listing_status_idx');
            $table->index(['classified_category_id', 'status'], 'exclusive_contracts_category_status_idx');
            $table->index(['marketer_id'], 'exclusive_contracts_marketer_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exclusive_contracts');
    }
};
