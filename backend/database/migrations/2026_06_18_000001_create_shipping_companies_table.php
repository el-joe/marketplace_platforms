<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_companies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->char('country_id', 36)->nullable();
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->string('contact_email');
            $table->string('contact_phone', 30)->nullable();
            $table->string('logo_path')->nullable();
            $table->json('served_countries')->nullable();
            $table->json('served_cities')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->tinyInteger('can_supervisors_receive_all_notifications')->default(1);
            $table->char('approved_by_admin_id', 36)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_companies');
    }
};
