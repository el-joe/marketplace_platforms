<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_section_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->enum('section', [
                'store_profile',
                'business_info',
                'contact_info',
                'documents',
                'bank_accounts',
            ]);
            $table->boolean('is_locked')->default(true);
            $table->string('locked_reason')->nullable()
                ->comment('Shown to vendor, e.g. "Under review"');
            $table->foreignUuid('locked_by_admin_id')->constrained('admins');
            $table->timestamp('locked_at');
            $table->foreignUuid('unlocked_by_admin_id')->nullable()->constrained('admins');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_section_locks');
    }
};
