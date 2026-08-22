<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_claim_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warranty_claim_id')
                ->constrained('warranty_claims')
                ->cascadeOnDelete();
            $table->uuid('sender_user_id')->index();
            $table->enum('sender_role', ['customer', 'vendor', 'admin']);
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_messages');
    }
};
