<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_agency_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_agency_id')->constrained('travel_agencies')->name('requests_travel_agency_id_fk');
            $table->foreignUuid('requested_by_travel_agency_member_id')->constrained('travel_agency_members')->name('requests_requested_by_member_id_fk');
            $table->enum('section', ['bank_accounts']);
            $table->enum('request_type', ['add', 'edit', 'delete'])->default('edit');
            $table->json('current_data');
            $table->json('requested_data');
            $table->text('agency_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignUuid('reviewed_by_admin_id')->nullable()->constrained('admins');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['travel_agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_agency_change_requests');
    }
};
