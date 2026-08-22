<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_acquisition_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->unsignedSmallInteger('commission_rate');
            $table->unsignedInteger('monthly_min_sales')->default(60);
            $table->unsignedInteger('monthly_max_sales')->default(100);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active');
            $table->unsignedBigInteger('total_earned')->default(0);
            $table->char('currency', 3);
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_acquisition_commissions');
    }
};
