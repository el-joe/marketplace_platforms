<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Aggregated metrics per A/B variant per day — written by nightly job
    public function up(): void
    {
        Schema::create('ab_test_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ab_test_id')->index();
            $table->char('variant', 1); // a|b
            $table->date('date');
            $table->integer('visitors')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('add_to_cart_count')->default(0);
            $table->integer('orders')->default(0);
            $table->bigInteger('revenue_cents')->default(0);
            $table->decimal('conversion_rate', 6, 4)->default(0);
            $table->bigInteger('revenue_per_visitor_cents')->default(0);
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — rows are immutable once written

            $table->unique(['ab_test_id', 'variant', 'date']);

            $table->foreign('ab_test_id')->references('id')->on('ab_tests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_results');
    }
};
