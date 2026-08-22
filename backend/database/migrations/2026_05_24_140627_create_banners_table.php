<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Table: platform_banners ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()country_idUUID FK→countriesYESNULLNULL = all countriesnameVARCHAR(150)NOInternal label: "Ramadan Hero May 2026"placement_codeVARCHAR(50)NOMatches a predefined slot. Full list belowmedia_idUUID FK→mediaNODesktop imagemobile_media_idUUID FK→mediaYESNULLMobile varianttitle_enVARCHAR(255)YESNULLOptional overlay texttitle_arVARCHAR(255)YESNULLsubtitle_enVARCHAR(500)YESNULLsubtitle_arVARCHAR(500)YESNULLcta_label_enVARCHAR(100)YESNULLcta_label_arVARCHAR(100)YESNULLcta_urlVARCHAR(500)YESNULLlink_typeVARCHAR(20)YESNULLproduct, category, flash_sale, page, urllink_reference_idUUIDYESNULLFK to the relevant entitystarts_atTIMESTAMPTZNOends_atTIMESTAMPTZNOstatusVARCHAR(20)NOdraftdraft, scheduled, active, ended, pauseddevice_targetVARCHAR(10)NOallall, desktop, mobile, appaudienceVARCHAR(20)NOallall, guest, logged_in, vip, new_userpriorityINTNO0When multiple banners compete for the same slot, higher winsimpressions_countBIGINTNO0Denormalized counterclicks_countBIGINTNO0created_by_admin_idUUID FK→usersNOupdated_by_admin_idUUID FK→usersYESNULLcreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()deleted_atTIMESTAMPTZYESNULL
         */
        Schema::create('banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index()->nullable();
            $table->string('name', 150);

            $table->enum('placement_code', [
                'homepage_hero',
                'homepage_secondary_left',
                'homepage_secondary_right',
                'homepage_midpage',
                'category_top_{slug}',
                'category_sidebar',
                'search_top',
                'cart_banner',
                'checkout_banner',
                'product_page_bottom',
                'app_splash',
                'app_home_top',
                'email_header'
            ]);
            // $table->uuid('media_id')->index();
            // $table->uuid('mobile_media_id')->index()->nullable();
            $table->string('title_en', 255)->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('subtitle_en', 500)->nullable();
            $table->string('subtitle_ar', 500)->nullable();
            $table->string('cta_label_en', 100)->nullable();
            $table->string('cta_label_ar', 100)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('link_type', 20)->nullable();
            $table->uuid('link_reference_id')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20);
            $table->string('device_target', 10);
            $table->string('audience', 20);
            $table->integer('priority')->default(0);
            $table->bigInteger('impressions_count')->default(0);
            $table->bigInteger('clicks_count')->default(0);
            $table->uuid('created_by_admin_id')->index();
            $table->uuid('updated_by_admin_id')->index()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
