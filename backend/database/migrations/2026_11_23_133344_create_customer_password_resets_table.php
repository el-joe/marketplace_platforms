<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token store for the `customers` password broker (storefront "forgot password").
     * The broker previously pointed at `password_reset_tokens`, which was never created.
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_password_resets')) {
            return;
        }

        Schema::create('customer_password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_resets');
    }
};
