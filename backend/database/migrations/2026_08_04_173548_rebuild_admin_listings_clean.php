<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── STEP 1: Drop all FKs referencing admin_product_listings ─────────────────
        // Drop before renaming/dropping to avoid constraint errors.
        $tables = [
            'ad_campaign_products', 'ad_clicks', 'cart_inventory_locks',
            'fbn_inbound_requests', 'flash_sale_analytics', 'flash_sale_submissions',
            'inventory_transfer_items', 'marketer_campaigns', 'marketplace_shipping_rules',
            'order_items', 'product_cost_references', 'return_request_message_attachments',
            'virtual_tryon_sessions', 'warehouse_inventories', 'wishlist_items',
            // Additional tables referencing admin_product_listings not listed in the
            // original spec — discovered via information_schema.KEY_COLUMN_USAGE.
            'ai_video_generation_jobs', 'cart_items', 'flash_sale_price_histories',
            'vendor_campaign_offer_products',
        ];
        foreach ($tables as $table) {
            try {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $fkName = $table . '_admin_product_listing_id_foreign';
                    $t->dropForeign($fkName);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropForeign(['admin_product_listing_id']);
                });
            } catch (\Throwable $e) {}
        }

        // ── STEP 2: Drop old table ───────────────────────────────────────────────────
        Schema::dropIfExists('admin_product_listings');

        // ── STEP 3: Rename admin_product_listing_id → admin_listing_id ──────────────
        // Several tables have CHECK constraints referencing the column being renamed —
        // MySQL refuses the rename while it's referenced, so drop and recreate them
        // with the clause rewritten to the new column name. Matched on either column
        // name so this step is safe to rerun after a partial prior run.
        $checkConstraints = DB::select("
            SELECT tc.TABLE_NAME, cc.CONSTRAINT_NAME,
                   REPLACE(cc.CHECK_CLAUSE, 'admin_product_listing_id', 'admin_listing_id') AS CHECK_CLAUSE
            FROM information_schema.CHECK_CONSTRAINTS cc
            JOIN information_schema.TABLE_CONSTRAINTS tc
              ON cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME AND cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
            WHERE cc.CONSTRAINT_SCHEMA = DATABASE()
              AND (cc.CHECK_CLAUSE LIKE '%admin_product_listing_id%' OR cc.CHECK_CLAUSE LIKE '%admin_listing_id%')
        ");

        foreach ($checkConstraints as $c) {
            DB::statement("ALTER TABLE `{$c->TABLE_NAME}` DROP CHECK `{$c->CONSTRAINT_NAME}`");
        }

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'admin_product_listing_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->renameColumn('admin_product_listing_id', 'admin_listing_id');
                });
            }
        }
        // Check constraints are recreated in STEP 7, after the admin_listing_id FKs
        // are added — MySQL forbids a column being in both a CHECK constraint and a
        // SET NULL foreign key referential action at the same time.

        // ── STEP 4: Remove nawy columns from categories ──────────────────────────────
        Schema::table('categories', function (Blueprint $t) {
            if (Schema::hasColumn('categories', 'nawy_sort_order')) $t->dropColumn('nawy_sort_order');
            if (Schema::hasColumn('categories', 'nawy_icon_path'))  $t->dropColumn('nawy_icon_path');
            if (Schema::hasColumn('categories', 'nawy_is_featured')) $t->dropColumn('nawy_is_featured');
        });

        // ── STEP 5: Clean nawy_now data ──────────────────────────────────────────────
        DB::table('app_contexts')->where('key', 'nawy_now')->delete();
        DB::table('block_types')->where('code', 'nawy_carousel')->delete();
        DB::table('page_blocks')
            ->where('app_context_key', 'nawy_now')
            ->update(['app_context_key' => null]);
        DB::table('pages')
            ->where('app_context_key', 'nawy_now')
            ->update(['app_context_key' => null]);

        // ── STEP 6: Create admin_listings ────────────────────────────────────────────
        // Drop any FKs left over from a prior partial run before dropping the table.
        $existingFks = DB::select("
            SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'admin_listings'
        ");
        foreach ($existingFks as $fk) {
            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
        Schema::dropIfExists('admin_listings');
        Schema::create('admin_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Core references
            $table->foreignUuid('warehouse_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('product_variant_id')
                  ->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('country_id')
                  ->constrained('countries')->cascadeOnDelete();

            // Pricing — BIGINT base-currency units, NO /100 anywhere
            $table->bigInteger('price');
            $table->bigInteger('compare_at_price')->nullable()
                  ->comment('Strikethrough "was" price shown on PDP');
            $table->bigInteger('cost_price')->nullable()
                  ->comment('Internal cost — NEVER exposed to customers, API, or vendor panel');
            $table->char('currency', 3);

            // Condition
            $table->enum('condition', ['new','like_new','good','acceptable','refurbished'])
                  ->default('new');
            $table->text('condition_notes')->nullable();

            // Fulfillment — ALWAYS fbn + express_fbn, enforced in model boot()
            $table->enum('fulfillment_model', ['fbm','fbn','cross_dock'])
                  ->default('fbn')
                  ->comment('Always fbn — enforced by model boot, no form field');
            $table->enum('global_system_type', ['express_fbn','merchant_fbp','marketplace'])
                  ->default('express_fbn')
                  ->comment('Always express_fbn — enforced by model boot');
            $table->foreignUuid('primary_shipping_method_id')->nullable()
                  ->constrained('shipping_methods')->nullOnDelete();
            $table->tinyInteger('is_global_shipping')->default(0);
            $table->tinyInteger('vendor_covers_delivery')->default(0)
                  ->comment('Platform absorbs remaining delivery gap — customer sees Free Delivery');

            // Inventory controls
            $table->integer('max_order_quantity')->nullable();
            $table->integer('low_stock_threshold')->default(5);
            $table->integer('total_sold')->default(0);

            // Buy box
            $table->boolean('buy_box_eligible')->default(true);
            $table->timestamp('buy_box_won_at')->nullable();

            // Status — no draft/pending_review/rejected for platform listings
            $table->enum('status', ['active','paused','out_of_stock','archived'])
                  ->default('active');

            // Campaign opt-in
            $table->boolean('campaign_enabled')->default(false);

            // Platform identity (Noon-style treatment)
            $table->string('sold_by_label_en', 150)->default('Nawy')
                  ->comment('Shown on PDP as "Sold by [name]". Default = platform name.');
            $table->string('sold_by_label_ar', 150)->default('نوي');
            $table->string('express_badge_label_en', 100)->default('Noon Express')
                  ->comment('Yellow Express badge label — always present, label customisable');
            $table->string('express_badge_label_ar', 100)->default('نون إكسبرس');
            $table->unsignedTinyInteger('search_boost')->default(10)
                  ->comment('Score bonus added during search ranking. Range 0-20. Default 10 = inherent advantage over vendor listings.');
            $table->boolean('is_daily_deal')->default(false)
                  ->comment('Pin in Deal of the Day homepage slots');
            $table->timestamp('daily_deal_ends_at')->nullable()
                  ->comment('Auto-disables is_daily_deal when reached. NULL = no expiry.');

            // A+ content (listing-level, not product-level)
            $table->json('aplus_images')->nullable()
                  ->comment('Extra product images for A+ strip on PDP. Array of CDN URLs, max 8.');
            $table->string('aplus_video_url', 500)->nullable()
                  ->comment('MP4/WebM product demo video URL for PDP.');
            $table->json('aplus_infographic_urls')->nullable()
                  ->comment('Feature callout banner images. Array of CDN URLs, max 5.');
            $table->text('aplus_headline_en')->nullable();
            $table->text('aplus_headline_ar')->nullable();

            // Physical dimensions (mirrors vendor_listings exactly)
            $table->enum('weight_class', ['light','medium','heavy'])->nullable()
                  ->comment('Display only: light ≤1000g, medium 1001–5000g, heavy >5000g');
            $table->enum('handling_class', ['standard','refrigerated','fragile','special_tech'])
                  ->default('standard');
            $table->unsignedInteger('declared_weight_grams')->nullable();
            $table->decimal('declared_length_cm', 8, 2)->nullable();
            $table->decimal('declared_width_cm', 8, 2)->nullable();
            $table->decimal('declared_height_cm', 8, 2)->nullable();

            // Ratings + scoring — populated by scheduled jobs, NEVER by forms
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->integer('rating_count')->default(0);
            $table->decimal('score', 8, 4)->nullable();
            $table->decimal('price_score', 5, 4)->nullable();
            $table->decimal('fulfillment_score', 5, 4)->nullable();
            $table->decimal('rating_score', 5, 4)->nullable();
            $table->decimal('availability_score', 5, 4)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('next_recalculate_at')->nullable();

            // Attribution
            $table->foreignUuid('created_by_admin_id')
                  ->constrained('admins')->cascadeOnDelete();
            $table->foreignUuid('updated_by_admin_id')->nullable()
                  ->constrained('admins')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['country_id', 'status']);
            $table->index(['product_variant_id', 'country_id']);
            $table->index('status');
            $table->index('buy_box_eligible');
            $table->index('is_daily_deal');
            $table->index('search_boost');
        });

        // ── STEP 7: Re-add admin_listing_id FKs on all tables ────────────────────────
        // Tables with an XOR check constraint against the column can't use
        // nullOnDelete — MySQL forbids SET NULL referential actions on a column
        // referenced by a CHECK constraint. Those tables originally used
        // restrictOnDelete(), so that behavior is preserved here.
        $checkConstrainedTables = array_unique(array_column($checkConstraints, 'TABLE_NAME'));

        foreach ($tables as $table) {
            $fkAlreadyExists = DB::selectOne("
                SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME = 'admin_listings'
            ", [$table]);

            if (!$fkAlreadyExists && Schema::hasColumn($table, 'admin_listing_id')) {
                Schema::table($table, function (Blueprint $t) use ($table, $checkConstrainedTables) {
                    $foreign = $t->foreign('admin_listing_id')->references('id')->on('admin_listings');
                    in_array($table, $checkConstrainedTables)
                        ? $foreign->restrictOnDelete()
                        : $foreign->nullOnDelete();
                });
            }
        }

        // Recreate the check constraints now that the FKs are in place.
        foreach ($checkConstraints as $c) {
            DB::statement("ALTER TABLE `{$c->TABLE_NAME}` ADD CONSTRAINT `{$c->CONSTRAINT_NAME}` CHECK {$c->CHECK_CLAUSE}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('Non-reversible — restore from backup.');
    }
};
