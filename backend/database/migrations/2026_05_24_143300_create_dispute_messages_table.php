<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispute_id')->index();
            $table->uuid('sender_user_id')->index();
            $table->enum('sender_role', ['customer', 'seller', 'admin']);
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_messages');
    }
};
