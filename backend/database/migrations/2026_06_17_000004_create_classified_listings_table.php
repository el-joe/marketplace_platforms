<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('listing_number', 30)->unique();
            $table->uuid('customer_id');
            $table->uuid('classified_category_id');
            $table->string('country_id');
            $table->uuid('city_id')->nullable();
            $table->enum('listing_purpose', ['sale', 'rent']);
            $table->string('title_en', 255);
            $table->string('title_ar', 255);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->bigInteger('price_cents');
            $table->char('currency', 3);
            $table->tinyInteger('price_negotiable')->default(0);
            $table->json('attributes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('sketch_file_path', 255)->nullable();
            $table->enum('status', [
                'draft', 'pending_contract', 'pending_review',
                'active', 'paused', 'sold', 'expired', 'rejected',
            ])->default('draft');
            $table->uuid('contract_template_id')->nullable();
            $table->timestamp('contract_accepted_at')->nullable();
            $table->text('contract_signature_data')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('approved_by_admin_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->date('expires_at')->nullable();
            $table->string('barcode_path', 255)->nullable();
            $table->tinyInteger('marketer_promotion_enabled')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('classified_category_id')->references('id')->on('classified_categories');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            $table->foreign('contract_template_id')->references('id')->on('classified_contract_templates')->nullOnDelete();
            $table->foreign('approved_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_listings');
    }
};
