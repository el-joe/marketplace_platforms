<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_acquisition_commission_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commission_id')->constrained('vendor_acquisition_commissions')->cascadeOnDelete();
            $table->foreignUuid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('order_count_in_month');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->timestamps();

            $table->unique(['commission_id', 'sub_order_id'], 'vac_earnings_commission_sub_order_unique');
            $table->index(['commission_id', 'month'], 'vac_earnings_commission_month_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_acquisition_commission_earnings');
    }
};
