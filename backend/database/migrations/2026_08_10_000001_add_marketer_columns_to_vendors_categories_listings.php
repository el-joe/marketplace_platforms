<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // vendors — marketer type (null = vendor only)
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('marketer_type', ['influencer', 'affiliate'])
                  ->nullable()
                  ->default(null)
                  ->after('account_manager_admin_id')
                  ->comment('null = vendor only; influencer/affiliate = vendor+marketer');
            $table->string('whatsapp_for_campaigns', 30)->nullable()->after('marketer_type')
                  ->comment('WhatsApp number for campaign invitation messages');
        });

        // categories — sample quotas + min stock for campaigns + marketer commission
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('influencer_sample_qty')->default(0)
                  ->after('is_featured')
                  ->comment('Samples per influencer marketer per campaign for this category');
            $table->unsignedSmallInteger('affiliate_sample_qty')->default(0)
                  ->after('influencer_sample_qty')
                  ->comment('Samples per affiliate marketer per campaign for this category');
            $table->unsignedSmallInteger('platform_sample_qty')->default(0)
                  ->after('affiliate_sample_qty')
                  ->comment('Platform-owned non-refundable samples per campaign for this category');
            $table->unsignedSmallInteger('min_stock_for_campaign')->default(10)
                  ->after('platform_sample_qty')
                  ->comment('Minimum vendor listing stock required to start a campaign');
        });

        // vendor_listings — campaign opt-in flag
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->boolean('campaign_enabled')->default(false)
                  ->after('declared_height_cm')
                  ->comment('Vendor opted this listing into a marketer campaign');
        });

        // admin_listings already has a campaign_enabled column, added when the table
        // was rebuilt in 2026_08_04_173548_rebuild_admin_listings_clean.
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['marketer_type', 'whatsapp_for_campaigns']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['influencer_sample_qty','affiliate_sample_qty','platform_sample_qty','min_stock_for_campaign']);
        });
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropColumn('campaign_enabled');
        });
    }
};
