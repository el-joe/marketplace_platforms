<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly promotion obligations per influencer category (client "second part"):
     *  - promotion_category_targets: admin-set monthly minimum per category (optionally per country)
     *  - marketer_profiles.promotion_category / monthly_promotion_target: which category a marketer is in,
     *    plus an optional per-marketer override of the category minimum
     *  - settings for the invitation window, WhatsApp provider and reminder lead-time
     */
    public function up(): void
    {
        Schema::create('promotion_category_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('promotion_category');
            $table->uuid('country_id')->nullable();
            $table->unsignedSmallInteger('monthly_minimum')->default(0);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->unique(['promotion_category', 'country_id'], 'pct_category_country_unique');
        });

        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('promotion_category')->nullable()->after('broker_serves_all_cities')
                ->comment('App\Enums\PromotionCategory value; NULL = no monthly obligation');
            $table->unsignedSmallInteger('monthly_promotion_target')->nullable()->after('promotion_category')
                ->comment('Per-marketer override of the category monthly minimum; NULL = use category target');
        });

        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->string('platform_fee_payer', 10)->nullable()->after('platform_fee_status')
                ->comment('marketer | vendor — whose wallet the influencer fee is charged to');
        });

        $settings = [
            ['marketer_influencer_fee_payer', 'vendor', 'marketer', 'Who pays the per-influencer platform fee: "vendor" (seller wallet, retried daily when the balance is short; client requirement) or "marketer" (influencer wallet)', 0],
            ['commission_vat_enabled', true, 'vendors', 'Charge VAT (at the order country rate) on the platform commission and deduct it from the seller payout', 0],
            ['marketer_promotion_reminder_days', 7, 'marketer', 'Days before month end when marketers still short of their monthly promotion minimum are warned (WhatsApp + in-app)', 0],
            ['whatsapp_provider', 'log', 'integrations', 'WhatsApp provider: "meta" (Cloud API) or "log" (nothing is delivered, only logged)', 0],
            ['whatsapp_meta_phone_number_id', '', 'integrations', 'WhatsApp Cloud API phone number id', 0],
            ['whatsapp_meta_access_token', '', 'integrations', 'WhatsApp Cloud API access token', 1],
        ];

        foreach ($settings as [$key, $value, $category, $description, $encrypted]) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'category' => $category, 'description' => $description, 'is_encrypted' => $encrypted, 'is_public' => 0]
            );
        }

        // Client wants a short acceptance window (e.g. 12h). Only touch the untouched legacy default (168h).
        $timeout = Setting::where('key', 'marketer_invitation_timeout_hours')->first();
        if ($timeout && (int) $timeout->value === 168) {
            $timeout->update([
                'value' => 12,
                'description' => 'Hours a marketer has to accept or reject a campaign invitation before the system picks a replacement marketer',
            ]);
            Setting::flush();
        }
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropColumn(['promotion_category', 'monthly_promotion_target']);
        });

        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropColumn('platform_fee_payer');
        });

        Schema::dropIfExists('promotion_category_targets');

        Setting::whereIn('key', [
            'marketer_influencer_fee_payer', 'commission_vat_enabled', 'marketer_promotion_reminder_days', 'whatsapp_provider', 'whatsapp_meta_phone_number_id', 'whatsapp_meta_access_token',
        ])->delete();
    }
};
