<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->foreignUuid('warehouse_id')->nullable()->after('id')
                ->constrained('warehouses')->nullOnDelete()
                ->comment('Which warehouse this listing ships from, used for exceptional zone / gap resolution.');

            $table->bigInteger('compare_at_price')->nullable()->after('price')
                ->comment('Strikethrough "was" price');

            $table->string('platform_sku', 100)->nullable()->after('cost_price')
                ->comment('Admin equivalent of vendor_sku');

            $table->enum('condition', ['new', 'like_new', 'good', 'acceptable', 'refurbished'])
                ->default('new')->after('platform_sku');
            $table->text('condition_notes')->nullable()->after('condition');

            $table->foreignUuid('primary_shipping_method_id')->nullable()->after('shipping_cost')
                ->constrained('shipping_methods')->nullOnDelete()
                ->comment('Resolved default shipping method for this listing.');
            $table->tinyInteger('is_global_shipping')->default(0)->after('primary_shipping_method_id');

            $table->integer('max_order_quantity')->nullable()->after('is_global_shipping');
            $table->integer('low_stock_threshold')->default(5)->after('max_order_quantity');
            $table->integer('total_sold')->default(0)->after('low_stock_threshold');

            $table->boolean('buy_box_eligible')->default(true)->after('total_sold');
            $table->timestampTz('buy_box_won_at')->nullable()->after('buy_box_eligible');

            $table->decimal('score', 8, 4)->nullable()->after('rating_count');
            $table->decimal('price_score', 5, 4)->nullable()->after('score');
            $table->decimal('fulfillment_score', 5, 4)->nullable()->after('price_score');
            $table->decimal('rating_score', 5, 4)->nullable()->after('fulfillment_score');
            $table->decimal('availability_score', 5, 4)->nullable()->after('rating_score');
            $table->timestampTz('calculated_at')->nullable()->after('availability_score');
            $table->timestampTz('next_recalculate_at')->nullable()->after('calculated_at');
            $table->index('score');

            $table->enum('weight_class', ['light', 'medium', 'heavy'])->nullable()->after('next_recalculate_at')
                ->comment('Display/filter helper only, not authoritative: light <= 1000g, medium 1001-5000g, heavy > 5000g');
            $table->enum('handling_class', ['standard', 'refrigerated', 'fragile', 'special_tech'])
                ->default('standard')->after('weight_class');
            $table->unsignedInteger('declared_weight_grams')->nullable()->after('handling_class');
            $table->decimal('declared_length_cm', 8, 2)->nullable()->after('declared_weight_grams');
            $table->decimal('declared_width_cm', 8, 2)->nullable()->after('declared_length_cm');
            $table->decimal('declared_height_cm', 8, 2)->nullable()->after('declared_width_cm');

            $table->decimal('influencer_commission_percentage', 5, 2)->nullable()->after('declared_height_cm')
                ->comment('Commission % offered to influencers to promote this listing; null = not opted in');
            $table->decimal('affiliate_commission_percentage', 5, 2)->nullable()->after('influencer_commission_percentage')
                ->comment('Commission % offered to affiliates/marketers to promote this listing; null = not opted in');
            $table->unsignedSmallInteger('influencer_sample_quota')->nullable()->after('affiliate_commission_percentage');
            $table->unsignedSmallInteger('affiliate_sample_quota')->nullable()->after('influencer_sample_quota');

            $table->softDeletes();
        });

        DB::statement("ALTER TABLE admin_product_listings MODIFY status ENUM('active', 'paused', 'out_of_stock', 'archived') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admin_product_listings MODIFY status ENUM('active', 'paused', 'archived') NOT NULL DEFAULT 'active'");

        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->dropIndex(['score']);
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('primary_shipping_method_id');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'compare_at_price',
                'platform_sku',
                'condition',
                'condition_notes',
                'is_global_shipping',
                'max_order_quantity',
                'low_stock_threshold',
                'total_sold',
                'buy_box_eligible',
                'buy_box_won_at',
                'score',
                'price_score',
                'fulfillment_score',
                'rating_score',
                'availability_score',
                'calculated_at',
                'next_recalculate_at',
                'weight_class',
                'handling_class',
                'declared_weight_grams',
                'declared_length_cm',
                'declared_width_cm',
                'declared_height_cm',
                'influencer_commission_percentage',
                'affiliate_commission_percentage',
                'influencer_sample_quota',
                'affiliate_sample_quota',
            ]);
        });
    }
};
