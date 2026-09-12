<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_category_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id');
            $table->uuid('category_id')->nullable()
                ->comment('Null = default rate applied across all categories for this marketer.');
            $table->decimal('commission_rate', 5, 2)->default(0)
                ->comment('Percentage, e.g. 8.00 = 8%.');
            $table->uuid('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['marketer_id', 'category_id']);

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('updated_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_category_commissions');
    }
};
