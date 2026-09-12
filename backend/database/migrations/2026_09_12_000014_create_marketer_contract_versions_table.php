<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_contract_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_contract_id');
            $table->unsignedInteger('version_number');
            $table->enum('content_type', ['pdf', 'text']);
            $table->string('file_url')->nullable()
                ->comment('Storage path for PDF contracts');
            $table->longText('text_content')->nullable()
                ->comment('For text/HTML contracts');
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('uploaded_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['marketer_contract_id', 'version_number'], 'mcv_contract_version_unique');
            $table->index(['marketer_contract_id', 'is_active'], 'mcv_contract_active_index');

            $table->foreign('marketer_contract_id')->references('id')->on('marketer_contracts')->cascadeOnDelete();
            $table->foreign('uploaded_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_contract_versions');
    }
};
