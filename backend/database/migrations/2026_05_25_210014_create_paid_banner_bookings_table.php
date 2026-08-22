<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Tracks who booked each paid banner slot and for how long
    // Separate from block config because booking history must survive config changes
    public function up(): void
    {
        Schema::create('paid_banner_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index(); // must be a paid_banner block
            $table->uuid('seller_id')->nullable()->index(); // FK → vendors; NULL = external brand
            $table->string('brand_name', 150)->nullable(); // for external brands not on platform
            $table->string('booking_reference', 50)->unique(); // BNR-2026-001
            $table->string('image_url', 500);
            $table->string('link_url', 500);
            $table->string('alt_text', 255);
            $table->string('pricing_model', 10); // cpm|cpc|fixed
            $table->bigInteger('rate_cents');
            $table->char('currency', 3);
            $table->bigInteger('total_charged_cents')->default(0);
            $table->date('booked_from');
            $table->date('booked_until');
            $table->string('status', 20)->default('pending'); // pending|active|completed|cancelled
            $table->integer('impressions_delivered')->default(0);
            $table->integer('clicks_delivered')->default(0);
            $table->uuid('booked_by_admin_id')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['page_block_id', 'status']);
            $table->index(['booked_from', 'booked_until']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('restrict');
            $table->foreign('seller_id')->references('id')->on('vendors')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_banner_bookings');
    }
};
