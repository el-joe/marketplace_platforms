<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('owner_type', ['customer', 'vendor', 'marketer', 'delivery_agent'])->index();
            $table->uuid('owner_id')->index();
            $table->bigInteger('balance_cents')->default(0);
            $table->bigInteger('pending_balance_cents')->default(0);
            $table->char('currency', 3);
            $table->boolean('is_frozen')->default(false);
            $table->text('frozen_reason')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'currency'], 'unique_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
