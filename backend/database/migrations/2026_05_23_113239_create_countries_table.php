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
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('iso_code_2', 2)->unique();
            $table->char('iso_code_3', 3)->unique();
            $table->string('name_ar')->unique();
            $table->string('name_en')->unique();
            $table->char('phone_prefix', 5)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('vat_rate', 5, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
