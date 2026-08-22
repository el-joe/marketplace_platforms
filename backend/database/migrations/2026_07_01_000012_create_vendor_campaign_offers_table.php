<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_campaign_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')
                ->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable()
                ->comment('Brief for the marketer — what the campaign is about, what content they should create, any brand guidelines');
            $table->text('requirements')->nullable()
                ->comment('What the vendor expects: post format, minimum posts, hashtags, disclosure requirements — shown to invited marketers');
            $table->enum('campaign_type', [
                'product_promotion',
                'store_promotion',
                'brand_deal',
                'product_specific',
                'flash_sale',
            ])->default('product_promotion')
                ->comment('Vendor-originated offers are limited to vendor-relevant types — no classified_promotion/travel_promotion here');
            $table->decimal('offered_commission_rate', 5, 2)->default(0)
                ->comment('The commission % the vendor is willing to pay to marketers who accept this offer');
            $table->enum('commission_type', ['percentage', 'flat_per_order', 'flat_per_click'])
                ->default('percentage');
            $table->bigInteger('budget_per_marketer_cents')->nullable()
                ->comment('Optional per-marketer budget cap — null = unlimited per marketer');
            $table->bigInteger('total_budget_cents')->nullable()
                ->comment('Total budget across ALL marketers who accept this offer');
            $table->bigInteger('total_spent_cents')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('invitation_deadline')->nullable()
                ->comment('Deadline for marketers to accept/decline — after this, pending invitations are auto-declined');
            $table->enum('status', [
                'draft',
                'pending_admin',
                'active',
                'paused',
                'ended',
                'cancelled',
            ])->default('draft');
            $table->foreignUuid('approved_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->enum('attribution_model', ['last_click', 'first_click', 'linear'])
                ->default('last_click');
            $table->boolean('whatsapp_sharing_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_campaign_offers');
    }
};
