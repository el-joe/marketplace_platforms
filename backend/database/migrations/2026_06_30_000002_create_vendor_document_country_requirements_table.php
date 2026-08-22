<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_document_country_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_document_type_id');
            $table->uuid('country_id');
            $table->foreign('vendor_document_type_id', 'vdcr_vdt_id_fk')->references('id')->on('vendor_document_types')->cascadeOnDelete();
            $table->foreign('country_id', 'vdcr_country_id_fk')->references('id')->on('countries')->cascadeOnDelete();
            $table->enum('requirement_level', ['mandatory', 'optional', 'not_applicable'])->default('optional');
            $table->timestamps();
            $table->unique(['vendor_document_type_id', 'country_id'], 'vdcr_vdt_country_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_document_country_requirements');
    }
};
