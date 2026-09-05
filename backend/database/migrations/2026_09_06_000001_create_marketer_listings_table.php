<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Owner
            $table->foreignUuid('marketer_id')->constrained('marketers')->cascadeOnDelete();

            // What is being listed
            $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained('countries')->cascadeOnDelete();

            // Campaign link (optional — can be stand-alone listing or tied to a campaign invitation)
            $table->foreignUuid('invitation_id')
                ->nullable()
                ->constrained('marketer_campaign_invitations')
                ->nullOnDelete()
                ->comment('Set when listing is promoted via a campaign invitation; null = independent listing');

            // Pricing — BIGINT base-currency. No /100.
            $table->bigInteger('price')->comment('Marketer\'s promoted price. BIGINT base-currency. No /100.');
            $table->bigInteger('compare_at_price')->nullable()->comment('Strikethrough price. BIGINT base-currency. No /100.');
            $table->char('currency', 3);

            // Status
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');

            // Condition (inherited from campaign listing condition, or set manually)
            $table->enum('condition', ['new', 'like_new', 'good', 'acceptable', 'refurbished'])->default('new');

            // Buy-box scoring (lower priority than admin and vendor by design)
            $table->decimal('score', 8, 4)->nullable();
            $table->timestamp('score_calculated_at')->nullable();

            // Referral tracking — populated from accepted invitation referral_code
            $table->string('referral_code', 50)->nullable()->unique();
            $table->string('referral_link', 500)->nullable();

            // Performance counters
            $table->unsignedInteger('total_sold')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for fast lookups
            $table->index(['product_variant_id', 'country_id', 'status'], 'ml_variant_country_status_idx');
            $table->index(['marketer_id', 'status'], 'ml_marketer_status_idx');
            $table->index(['country_id', 'status', 'score'], 'ml_country_status_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_listings');
    }
};
