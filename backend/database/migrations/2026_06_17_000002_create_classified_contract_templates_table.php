<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_contract_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classified_category_id')->nullable();
            $table->string('name', 200);
            $table->integer('version')->default(1);
            $table->longText('content_en');
            $table->longText('content_ar');
            $table->tinyInteger('is_active')->default(1);
            $table->uuid('created_by_admin_id');
            $table->timestamps();

            $table->foreign('created_by_admin_id')->references('id')->on('admins');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_contract_templates');
    }
};
