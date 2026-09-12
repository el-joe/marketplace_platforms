<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_ad_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ad_package_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->bigInteger('amount_paid');
            $table->char('currency', 3)->default('AED');
            $table->string('popup_title_en')->nullable();
            $table->string('popup_title_ar')->nullable();
            $table->text('popup_body_en')->nullable();
            $table->text('popup_body_ar')->nullable();
            $table->string('popup_image_url')->nullable();
            $table->string('popup_cta_url')->nullable();
            $table->timestamps();

            $table->index(['vendor_listing_id', 'status', 'ends_at']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ad_subscriptions');
    }
};
