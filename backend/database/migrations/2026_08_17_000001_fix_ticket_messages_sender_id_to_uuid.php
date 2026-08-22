<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['sender_type', 'sender_id']);
            $table->uuid('sender_id')->change();
            $table->index(['sender_type', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['sender_type', 'sender_id']);
            $table->unsignedBigInteger('sender_id')->change();
            $table->index(['sender_type', 'sender_id']);
        });
    }
};
