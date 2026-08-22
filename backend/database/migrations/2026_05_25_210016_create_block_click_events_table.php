<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Raw click events before aggregation into block_analytics
    // Purged after 7 days once aggregated
    // Partition by clicked_at weekly in production
    public function up(): void
    {
        Schema::create('block_click_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->uuid('user_id')->nullable()->index(); // NULL for guests
            $table->string('session_id', 100);
            $table->string('click_target', 500); // URL or product_id clicked
            $table->string('click_target_type', 30); // product|category|url|cta
            $table->string('device_type', 10); // desktop|mobile|app
            $table->uuid('country_id')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('clicked_at')->useCurrent();
            // No updated_at — events are immutable

            $table->index(['page_block_id', 'clicked_at']);
            $table->index('clicked_at'); // purge job: DELETE WHERE clicked_at < now() - 7 days

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_click_events');
    }
};
