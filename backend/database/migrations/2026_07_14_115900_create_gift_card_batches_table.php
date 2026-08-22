<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedBigInteger('amount');
            $table->char('currency_code', 3);

            $table->unsignedInteger('quantity');

            $table->timestamp('expires_at')->nullable();

            $table->uuid('created_by_admin_id')->nullable();
            $table->foreign('created_by_admin_id')->references('id')->on('admins')->nullOnDelete();

            $table->timestamps();

            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_batches');
    }
};
