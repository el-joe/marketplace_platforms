<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_special_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete()
                  ->comment('Category of the request, e.g. Real Estate, Cars');
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete()
                  ->comment('City where the customer needs the service; NULL = flexible');
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->text('description_en');
            $table->text('description_ar')->nullable();
            $table->bigInteger('budget')->nullable()
                  ->comment("Customer's budget. BIGINT base-currency. No /100.");
            $table->char('budget_currency', 3)->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->unsignedSmallInteger('brokers_notified')->default(0)
                  ->comment('Count of brokers auto-notified on creation');
            $table->timestamps();

            $table->index(['category_id', 'city_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_special_requests');
    }
};
