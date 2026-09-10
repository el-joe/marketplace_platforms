<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_daily_stats', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->char('paid_ad_booking_id', 36);
            $t->date('date');
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->bigInteger('spend')->default(0);
            $t->char('currency', 3);
            $t->timestamps();
            $t->unique(['paid_ad_booking_id', 'date']);
            $t->foreign('paid_ad_booking_id')->references('id')->on('paid_ad_bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_daily_stats');
    }
};
