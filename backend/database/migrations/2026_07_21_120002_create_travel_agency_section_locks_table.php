<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_agency_section_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_agency_id')->constrained('travel_agencies')->cascadeOnDelete();
            $table->enum('section', ['bank_accounts']);
            $table->boolean('is_locked')->default(true);
            $table->string('locked_reason')->nullable();
            $table->foreignUuid('locked_by_admin_id')->constrained('admins');
            $table->timestamp('locked_at');
            $table->foreignUuid('unlocked_by_admin_id')->nullable()->constrained('admins');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['travel_agency_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_agency_section_locks');
    }
};
