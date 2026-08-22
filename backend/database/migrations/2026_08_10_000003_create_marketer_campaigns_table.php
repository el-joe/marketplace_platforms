<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Creator
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();

            // Listing (XOR — enforced by CHECK below)
            $table->foreignUuid('vendor_listing_id')->nullable()->constrained('vendor_listings')->restrictOnDelete();
            $table->foreignUuid('admin_listing_id')->nullable()->constrained('admin_listings')->restrictOnDelete();

            $table->foreignUuid('country_id')->constrained('countries');
            $table->char('currency', 3);

            // Commission model
            $table->enum('commission_type', ['fixed', 'tiered', 'last_click'])
                  ->comment('fixed: flat per sale; tiered: per order milestone; last_click: last referral wins');

            // Vendor sets a max budget for the campaign commission pool
            $table->bigInteger('max_commission_budget')->default(0)
                  ->comment('Max total commission vendor will pay. BIGINT base-currency. No /100.');

            // Admin splits the budget: platform fee + marketer share
            $table->bigInteger('platform_commission_amount')->default(0)
                  ->comment('Admin-determined platform cut. BIGINT base-currency. No /100.');
            $table->bigInteger('marketer_commission_amount')->default(0)
                  ->comment('Commission per marketer per sale. BIGINT base-currency. No /100.');

            // Admin review
            $table->foreignUuid('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->enum('status', [
                'pending_admin',   // waiting for admin approve/reject
                'active',          // approved and live
                'auto_approved',   // approved automatically after 36hr timeout
                'rejected',        // admin rejected
                'paused',          // vendor/admin paused
                'done',            // product out of stock, auto-closed
                'cancelled',       // vendor cancelled before approval
            ])->default('pending_admin');

            // Auto-approve: if admin doesn't act within 36hr
            $table->timestamp('auto_approve_at')->nullable()
                  ->comment('Campaign auto-approves at this timestamp if admin has not acted');
            $table->boolean('auto_approved')->default(false);

            // Sample config (snapshotted from category at campaign creation time)
            $table->unsignedSmallInteger('platform_sample_qty_snapshot')->default(0);
            $table->unsignedSmallInteger('per_marketer_sample_qty_snapshot')->default(0)
                  ->comment('Samples per marketer (influencer or affiliate qty from category)');

            // Campaign metadata
            $table->string('title', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // XOR CHECK: exactly one listing type
        DB::statement("ALTER TABLE marketer_campaigns ADD CONSTRAINT chk_campaign_listing_xor
            CHECK (
                (vendor_listing_id IS NOT NULL AND admin_listing_id IS NULL) OR
                (vendor_listing_id IS NULL AND admin_listing_id IS NOT NULL)
            )");
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaigns');
    }
};
