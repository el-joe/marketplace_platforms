<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_schedule_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('radio_channel_id')->constrained('radio_channels')->cascadeOnDelete();
            $table->string('title', 200);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('recurrence', ['once', 'daily', 'weekly'])->default('once');
            $table->json('recurrence_days')->nullable(); // ["mon","wed","fri"]
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_schedule_slots');
    }
};
