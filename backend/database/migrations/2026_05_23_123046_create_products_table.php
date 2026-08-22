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
         * ColumnTypeNullDescriptionidUUID PKNOcategory_idUUID FK→categoriesNOLeaf category onlybrand_idUUID FK→brandsYESname_enVARCHAR(255)NOCanonical global namename_arVARCHAR(255)NOslugVARCHAR(255) UNIQUENOGlobal slug. Country sites may add prefix: /egy/p/slugmodel_numberVARCHAR(100)YESManufacturer model: MQ9R3LL/AgtinVARCHAR(14)YESEAN / UPC / ISBN — unique identifierdescription_enTEXTYESRich HTMLdescription_arTEXTYESshort_desc_enVARCHAR(500)YESFor listing cardsshort_desc_arVARCHAR(500)YESstatusVARCHAR(20)NOdraft, active, discontinued, restrictedis_featuredBOOLEANNOAdmin can feature platform-widerequires_brand_authBOOLEANNOSellers must upload authorization letteris_age_restrictedBOOLEANNOmin_ageTINYINTYES18, 21is_hazardousBOOLEANNOAffects shipping optionshas_variantsBOOLEANNOtrue = size/color matrix. false = single SKUai_quality_scoreDECIMAL(3,2)YES0–10. Set by ReviewProductJobseller_countINTNODenormalized. Active sellers listing this productrating_avgDECIMAL(3,2)NOAcross all countries and sellersrating_countINTNOtotal_soldINTNOAcross all countries and sellersview_countBIGINTNOseo_titleVARCHAR(255)YESGlobal default. Overridden per country in product_country_settingsseo_descriptionTEXTYESpublished_atTIMESTAMPTZYESWhen first set to activecreated_by_admin_idUUID FK→usersYESWhich admin created this catalog entrycreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNOdeleted_atTIMESTAMPTZYES
         */
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->index();
            $table->uuid('brand_id')->index()->nullable();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('slug')->unique();
            $table->string('model_number', 100)->nullable();
            $table->string('gtin', 14)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('short_desc_en', 500)->nullable();
            $table->string('short_desc_ar', 500)->nullable();
            $table->enum('status', ['draft', 'active', 'discontinued', 'restricted']);
            $table->boolean('is_featured')->default(false);
            // $table->boolean('requires_brand_auth')->default(false);
            $table->boolean('is_age_restricted')->default(false);
            $table->tinyInteger('min_age')->nullable();
            $table->boolean('is_hazardous')->default(false);
            $table->boolean('has_variants')->default(false);
            // $table->decimal('ai_quality_score', 3, 2)->nullable();
            $table->integer('seller_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->integer('rating_count')->default(0);
            $table->integer('total_sold')->default(0);
            $table->bigInteger('view_count')->default(0);
            $table->string('seo_title_en', 255)->nullable();
            $table->string('seo_title_ar', 255)->nullable();
            $table->text('seo_description_en')->nullable();
            $table->text('seo_description_ar')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->uuid('created_by_admin_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
