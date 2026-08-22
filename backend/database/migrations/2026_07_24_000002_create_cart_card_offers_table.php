<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_card_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('country_id');
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();

            $table->string('card_name_en', 150);
            $table->string('card_name_ar', 150)->nullable();
            $table->string('bank_name_en', 100)->nullable();
            $table->string('bank_name_ar', 100)->nullable();
            $table->string('card_image_path', 500)->nullable();

            $table->enum('cashback_type', ['percentage', 'fixed']);
            $table->decimal('cashback_pct', 5, 2)->default(0.00);
            $table->unsignedBigInteger('cashback_fixed_amount')->default(0);

            $table->string('label_template_en', 300)
                ->default('Earn {amount} CA$HBACK with {card_name}');
            $table->string('label_template_ar', 300)->nullable();
            $table->string('apply_url', 500)->nullable();
            $table->string('apply_label_en', 100)->default('Apply');
            $table->string('apply_label_ar', 100)->default('قدم الآن');

            $table->unsignedBigInteger('min_order_amount')->default(0);
            $table->unsignedBigInteger('max_cashback_amount')->nullable();

            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by_admin_id')->nullable();
            $table->foreign('created_by_admin_id')->references('id')->on('admins')->nullOnDelete();

            $table->timestamps();

            $table->index(['country_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_card_offers');
    }
};
