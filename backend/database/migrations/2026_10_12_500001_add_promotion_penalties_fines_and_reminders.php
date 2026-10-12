<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P12: per-category penalty, admin-curated listing category (3 intermediaries / 4 Nawy),
     * the month-close fine ledger and the once-per-month reminder log.
     */
    public function up(): void
    {
        Schema::table('promotion_category_targets', function (Blueprint $table) {
            $table->unsignedBigInteger('penalty_amount')->default(0)->after('monthly_minimum')
                ->comment('Flat fine (country currency base units) when the month closes below the minimum; 0 = no fine');
        });

        Schema::table('admin_listings', function (Blueprint $table) {
            $table->unsignedTinyInteger('promotion_category')->nullable()->after('campaign_enabled')
                ->comment('App\Enums\PromotionCategory 3 (intermediaries) or 4 (Nawy products); NULL = not in a quota category');
            $table->index('promotion_category');
        });

        Schema::create('marketer_promotion_fines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id');
            $table->unsignedTinyInteger('promotion_category');
            $table->char('period', 7)->comment('Y-m of the closed month');
            $table->unsignedSmallInteger('target');
            $table->unsignedSmallInteger('done');
            $table->unsignedBigInteger('amount')->default(0);
            $table->char('currency', 3)->nullable();
            $table->string('status', 12)->default('pending')->comment('pending | deducted | waived');
            $table->uuid('wallet_transaction_id')->nullable();
            $table->timestamp('deducted_at')->nullable();
            $table->uuid('waived_by_admin_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->unique(['marketer_id', 'promotion_category', 'period'], 'mpf_marketer_category_period_unique');
            $table->index(['status', 'period']);
        });

        Schema::create('marketer_promotion_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_id');
            $table->unsignedTinyInteger('promotion_category');
            $table->char('period', 7);
            $table->unsignedSmallInteger('done');
            $table->unsignedSmallInteger('target');
            $table->boolean('whatsapp_sent')->default(false);
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->unique(['marketer_id', 'promotion_category', 'period'], 'mpr_marketer_category_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_promotion_reminders');
        Schema::dropIfExists('marketer_promotion_fines');

        Schema::table('admin_listings', function (Blueprint $table) {
            $table->dropIndex(['promotion_category']);
            $table->dropColumn('promotion_category');
        });

        Schema::table('promotion_category_targets', function (Blueprint $table) {
            $table->dropColumn('penalty_amount');
        });
    }
};
