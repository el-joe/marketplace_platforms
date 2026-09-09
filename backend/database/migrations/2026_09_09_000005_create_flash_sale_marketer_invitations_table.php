<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flash_sale_marketer_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->decimal('extra_commission_rate', 5, 2)->nullable();
            $table->foreignUuid('invited_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['flash_sale_id', 'marketer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_marketer_invitations');
    }
};
