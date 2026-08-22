<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_dismissed_announcement_bars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->index();
            $table->uuid('announcement_bar_id')->index();
            $table->timestampTz('dismissed_at');
            $table->unique(['customer_id', 'announcement_bar_id'], 'cdab_customer_bar_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_dismissed_announcement_bars');
    }
};
