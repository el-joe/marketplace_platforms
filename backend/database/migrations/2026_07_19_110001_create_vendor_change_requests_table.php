<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors');
            $table->foreignUuid('requested_by_vendor_admin_id')->constrained('vendor_admins');
            $table->enum('section', [
                'store_profile',
                'business_info',
                'contact_info',
                'documents',
                'bank_accounts',
            ]);
            $table->enum('request_type', ['edit', 'add', 'delete'])->default('edit');
            $table->json('current_data')
                ->comment('Snapshot of what the values are right now');
            $table->json('requested_data')
                ->comment('What the vendor wants them to change to');
            $table->text('vendor_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending');
            $table->foreignUuid('reviewed_by_admin_id')->nullable()->constrained('admins');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_change_requests');
    }
};
