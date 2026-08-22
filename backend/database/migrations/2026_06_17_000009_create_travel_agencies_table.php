<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->string('license_number', 100)->nullable();
            $table->foreignUuid('country_id')->constrained('countries');
            $table->string('logo_path')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->foreignUuid('approved_by_admin_id')->nullable()->constrained('admins');
            $table->timestamp('approved_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_agencies');
    }
};
