<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_message_id')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
