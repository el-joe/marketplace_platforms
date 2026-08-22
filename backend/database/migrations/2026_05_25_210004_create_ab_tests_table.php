<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Created before page_blocks so that page_blocks can FK here
    public function up(): void
    {
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id')->index();
            $table->string('name', 150);
            $table->text('hypothesis')->nullable();
            $table->string('status', 20)->default('draft'); // draft|running|paused|concluded
            $table->integer('traffic_split_pct')->default(50); // % seeing variant B
            $table->char('winner', 1)->nullable(); // a|b
            $table->string('primary_metric', 50)->default('conversion_rate');
            $table->integer('min_sample_size')->default(1000);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('result_notes')->nullable();
            $table->uuid('created_by_admin_id')->index();
            $table->timestamps();

            $table->index(['page_id', 'status']);
            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_tests');
    }
};
