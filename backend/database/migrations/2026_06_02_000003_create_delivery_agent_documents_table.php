<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_agent_documents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('agent_id')->constrained('delivery_agents');
            $table->enum('document_type', [
                'national_id',
                'driving_license',
                'vehicle_registration',
                'insurance',
                'profile_photo',
            ]);
            $table->string('file_path', 255);
            $table->enum('status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->char('verified_by_admin_id', 36)->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_agent_documents');
    }
};
