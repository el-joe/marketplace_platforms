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
         * Table: banner_placement_definitions The canonical registry of all placements. Lets you add new placements without code deployment. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()codeVARCHAR(50) UNIQUENOhomepage_heronameVARCHAR(150)NO"Homepage hero slider"descriptionTEXTYESNULLWhere it appears, what it looks likewidth_pxINTNORecommended widthheight_pxINTNORecommended heightmax_file_size_kbINTNO500Upload limitallowed_formatsJSONBNO["jpg","png","webp"]device_restrictionVARCHAR(10)NOallall, desktop, mobile, appmax_simultaneousINTNO1How many banners can show at once in this slotsupports_vendor_adsBOOLEANNOfalseWhether vendors can book this slotbase_rate_weekly_centsBIGINTYESNULLIf supports_vendor_ads, the starting priceis_activeBOOLEANNOtruesort_orderINTNO0created_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('banner_placement_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->integer('width_px');
            $table->integer('height_px');
            $table->integer('max_file_size_kb');
            $table->json('allowed_formats');
            $table->string('device_restriction', 10);
            $table->integer('max_simultaneous');
            $table->boolean('supports_vendor_ads');
            $table->bigInteger('base_rate_weekly_cents')->nullable();
            $table->boolean('is_active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_placement_definitions');
    }
};
