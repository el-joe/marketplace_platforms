<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_contract_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_contract_version_id');
            $table->uuid('customer_id');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('accepted_at')->useCurrent();
            $table->uuid('order_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'marketer_contract_version_id'], 'mca_customer_version_index');
            $table->index('order_id');

            $table->foreign('marketer_contract_version_id', 'mca_version_foreign')->references('id')->on('marketer_contract_versions')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_contract_acceptances');
    }
};
