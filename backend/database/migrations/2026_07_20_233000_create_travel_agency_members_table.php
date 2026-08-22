<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_agency_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('travel_agency_id')->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 100)->nullable();
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('travel_agency_id')->references('id')->on('travel_agencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_agency_members');
    }
};
