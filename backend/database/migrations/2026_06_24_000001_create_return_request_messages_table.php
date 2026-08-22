<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->char('sender_user_id', 36)->index();
            $table->enum('sender_role', ['customer', 'seller', 'admin']);
            $table->text('message');
            $table->tinyInteger('is_internal_note')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['return_request_id', 'is_internal_note', 'created_at'], 'rrm_request_note_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_messages');
    }
};
