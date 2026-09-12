<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingCountry = DB::table('paid_ad_slots')->whereNull('country_id')->exists();
        if ($missingCountry) {
            throw new RuntimeException(
                'paid_ad_slots has rows with a NULL country_id — assign a country to every slot before running this migration.'
            );
        }

        Schema::table('paid_ad_slots', function (Blueprint $t) {
            $t->char('country_id', 36)->nullable(false)->change();
            $t->char('placement_definition_id', 36)->nullable()->change();

            $t->enum('target_type', ['placement', 'page_block'])->default('placement')->after('id');
            $t->char('page_block_id', 36)->nullable()->after('placement_definition_id');
            $t->unsignedSmallInteger('item_position')->nullable()->after('page_block_id')
                ->comment('1-based visual index inside the block. NULL = whole block (full_banner).');
            $t->enum('fill_mode', ['replace', 'insert'])->default('replace')->after('item_position');
            $t->char('category_id', 36)->nullable()->after('country_id')
                ->comment('Optional scope for category_top / product_page_bottom placements.');

            $t->string('name_ar', 150)->nullable()->after('name');
            $t->text('notes_for_vendors_ar')->nullable()->after('notes_for_vendors');

            $t->unsignedInteger('creative_width_px')->nullable();
            $t->unsignedInteger('creative_height_px')->nullable();
            $t->unsignedInteger('mobile_width_px')->nullable();
            $t->unsignedInteger('mobile_height_px')->nullable();

            $t->unsignedTinyInteger('max_concurrent')->default(1)
                ->comment('Bookings that may run on the same day (rotation).');
            $t->unsignedSmallInteger('lead_time_days')->default(1)
                ->comment('Minimum days between booking submit and booked_from.');
            $t->bigInteger('min_budget')->nullable()->comment('CPM/CPC minimum budget, base currency.');
            $t->enum('allowed_advertisers', ['vendor', 'marketer', 'both'])->default('vendor');
            $t->integer('sort_order')->default(0);
            $t->softDeletes();

            $t->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $t->foreign('placement_definition_id')->references('id')->on('banner_placement_definitions')->restrictOnDelete();
            $t->foreign('page_block_id')->references('id')->on('page_blocks')->restrictOnDelete();
            $t->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $t->index(['target_type', 'country_id', 'is_available']);
            $t->index(['page_block_id', 'item_position']);
        });

        DB::statement("ALTER TABLE paid_ad_slots MODIFY pricing_model
            ENUM('fixed_daily','fixed_weekly','fixed_monthly','cpm','cpc') NOT NULL");
    }

    public function down(): void
    {
        $usesFixedDaily = DB::table('paid_ad_slots')->where('pricing_model', 'fixed_daily')->exists();
        if ($usesFixedDaily) {
            throw new RuntimeException(
                'paid_ad_slots has rows with pricing_model = fixed_daily — cannot roll back the enum without a plan for those rows.'
            );
        }

        DB::statement("ALTER TABLE paid_ad_slots MODIFY pricing_model
            ENUM('fixed_weekly','fixed_monthly','cpm','cpc') NOT NULL");

        Schema::table('paid_ad_slots', function (Blueprint $t) {
            $t->dropForeign(['country_id']);
            $t->dropForeign(['placement_definition_id']);
            $t->dropForeign(['page_block_id']);
            $t->dropForeign(['category_id']);
            $t->dropIndex(['target_type', 'country_id', 'is_available']);
            $t->dropIndex(['page_block_id', 'item_position']);

            $t->dropSoftDeletes();
            $t->dropColumn([
                'sort_order',
                'allowed_advertisers',
                'min_budget',
                'lead_time_days',
                'max_concurrent',
                'mobile_height_px',
                'mobile_width_px',
                'creative_height_px',
                'creative_width_px',
                'notes_for_vendors_ar',
                'name_ar',
                'category_id',
                'fill_mode',
                'item_position',
                'page_block_id',
                'target_type',
            ]);

            $t->char('placement_definition_id', 36)->nullable(false)->change();
            $t->char('country_id', 36)->nullable()->change();
        });
    }
};
