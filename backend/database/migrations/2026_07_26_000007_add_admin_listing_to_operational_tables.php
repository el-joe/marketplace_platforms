<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── virtual_tryon_sessions ──────────────────────────────────────────
        Schema::table('virtual_tryon_sessions', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE virtual_tryon_sessions MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE virtual_tryon_sessions
            ADD CONSTRAINT chk_vts_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);

        // ── ai_video_generation_jobs ────────────────────────────────────────
        // vendor_listing_id is already nullable. requested_by_type enum('vendor','marketer')
        // is left unchanged here — admin-initiated AI video jobs are not yet a defined
        // business flow; extend the enum in a follow-up migration once that's confirmed.
        Schema::table('ai_video_generation_jobs', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->nullOnDelete();
        });

        // ── inventory_transfer_items ────────────────────────────────────────
        Schema::table('inventory_transfer_items', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE inventory_transfer_items MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_transfer_items
            ADD CONSTRAINT chk_iti_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);

        // ── fbn_inbound_requests ────────────────────────────────────────────
        Schema::table('fbn_inbound_requests', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE fbn_inbound_requests MODIFY vendor_id CHAR(36) NULL');
        DB::statement('ALTER TABLE fbn_inbound_requests MODIFY vendor_listing_id CHAR(36) NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE fbn_inbound_requests
            ADD CONSTRAINT chk_fir_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_product_listing_id IS NULL)
                OR
                (vendor_listing_id IS NULL AND admin_product_listing_id IS NOT NULL)
            )
        SQL);

        // ── product_cost_references ─────────────────────────────────────────
        // vendor_listing_id is already nullable.
        Schema::table('product_cost_references', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->nullOnDelete();
        });

        // ── vendor_campaign_offer_products ──────────────────────────────────
        Schema::table('vendor_campaign_offer_products', function (Blueprint $table) {
            $table->dropForeign(['vendor_campaign_offer_id']);
            $table->dropForeign(['vendor_listing_id']);
            $table->dropUnique('v_c_o_products_unique');
        });

        Schema::table('vendor_campaign_offer_products', function (Blueprint $table) {
            $table->char('admin_product_listing_id', 36)->nullable()->after('vendor_listing_id')->index();
            $table->foreign('admin_product_listing_id')->references('id')->on('admin_product_listings')->nullOnDelete();
            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
            $table->foreign('vendor_campaign_offer_id')->references('id')->on('vendor_campaign_offers')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE vendor_campaign_offer_products MODIFY vendor_listing_id CHAR(36) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vendor_campaign_offer_products MODIFY vendor_listing_id CHAR(36) NOT NULL');

        Schema::table('vendor_campaign_offer_products', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        Schema::table('vendor_campaign_offer_products', function (Blueprint $table) {
            $table->unique(['vendor_campaign_offer_id', 'vendor_listing_id'], 'v_c_o_products_unique');
        });

        Schema::table('product_cost_references', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE fbn_inbound_requests DROP CHECK chk_fir_listing_xor');

        Schema::table('fbn_inbound_requests', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE fbn_inbound_requests MODIFY vendor_listing_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE fbn_inbound_requests MODIFY vendor_id CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE inventory_transfer_items DROP CHECK chk_iti_listing_xor');

        Schema::table('inventory_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE inventory_transfer_items MODIFY vendor_listing_id CHAR(36) NOT NULL');

        Schema::table('ai_video_generation_jobs', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE virtual_tryon_sessions DROP CHECK chk_vts_listing_xor');

        Schema::table('virtual_tryon_sessions', function (Blueprint $table) {
            $table->dropForeign(['admin_product_listing_id']);
            $table->dropColumn('admin_product_listing_id');
        });

        DB::statement('ALTER TABLE virtual_tryon_sessions MODIFY vendor_listing_id CHAR(36) NOT NULL');
    }
};
