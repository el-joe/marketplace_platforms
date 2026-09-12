<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('paid_banner_bookings')->exists()) {
            throw new RuntimeException('paid_banner_bookings has rows — convert them to page_block slots + bookings before dropping.');
        }

        Schema::dropIfExists('paid_banner_bookings');
    }

    public function down(): void
    {
        Schema::create('paid_banner_bookings', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->char('page_block_id', 36);
            $t->char('seller_id', 36)->nullable();
            $t->string('brand_name', 150)->nullable();
            $t->string('booking_reference', 50);
            $t->string('image_url', 500);
            $t->string('link_url', 500);
            $t->string('alt_text', 255);
            $t->string('pricing_model', 10);
            $t->bigInteger('rate');
            $t->char('currency', 3);
            $t->bigInteger('total_charged')->default(0);
            $t->date('booked_from');
            $t->date('booked_until');
            $t->string('status', 20)->default('pending');
            $t->integer('impressions_delivered')->default(0);
            $t->integer('clicks_delivered')->default(0);
            $t->char('booked_by_admin_id', 36);
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique('booking_reference');
            $t->index(['page_block_id', 'status']);
            $t->index('page_block_id');
            $t->index('seller_id');
            $t->index('booked_by_admin_id');
            $t->index(['booked_from', 'booked_until']);

            $t->foreign('page_block_id')->references('id')->on('page_blocks')->restrictOnDelete();
            $t->foreign('seller_id')->references('id')->on('vendors')->nullOnDelete();
        });
    }
};
