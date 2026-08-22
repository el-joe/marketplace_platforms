<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->uuid('country_id')->index()->nullable();
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active');
            $table->date('date_of_birth')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0.00);
            $table->string('referral_code')->nullable();
            $table->uuid('referred_by')->nullable()->index();
            $table->decimal('loyalty_points', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
