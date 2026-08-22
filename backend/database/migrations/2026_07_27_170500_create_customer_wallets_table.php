<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('customer_id')->unique();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();

            $table->unsignedBigInteger('balance')->default(0);
            $table->char('currency_code', 3);

            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};
