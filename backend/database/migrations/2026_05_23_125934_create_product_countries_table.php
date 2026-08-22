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
        //ColumnTypeNullDescriptionidUUID PKNOproduct_idUUID FK→productsNOcountry_idUUID FK→countriesNOis_availableBOOLEANNOfalse = product hidden on this country's site entirelyunavailable_reasonVARCHAR(100)YESlegal_restriction, certification_pending, customs_issue, not_launchedname_override_enVARCHAR(255)YESCountry-specific name. NULL = use globalname_override_arVARCHAR(255)YESdescription_override_enTEXTYESAdditional regulatory text, warningsdescription_override_arTEXTYESrequires_local_certBOOLEANNOe.g. SASO in KSA, ESMA in UAEcertification_bodyVARCHAR(100)YES"SASO", "ESMA", "ETA"certification_notesTEXTYESis_age_restrictedBOOLEANNOCountry may restrict even if globally notmin_ageTINYINTYESseo_titleVARCHAR(255)YESCountry-specific SEO titleseo_descriptionTEXTYESmade_available_atTIMESTAMPTZYESmade_unavailable_atTIMESTAMPTZYESupdated_by_admin_idUUID FK→usersYEScreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNO
        Schema::create('product_countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->uuid('country_id')->index();
            $table->boolean('is_available')->default(true);
            $table->string('unavailable_reason', 100)->nullable();
            $table->string('name_override_en', 255)->nullable();
            $table->string('name_override_ar', 255)->nullable();
            $table->text('description_override_en')->nullable();
            $table->text('description_override_ar')->nullable();
            $table->boolean('requires_local_cert')->default(false);
            // $table->string('certification_body', 100)->nullable();
            // $table->text('certification_notes')->nullable();
            $table->boolean('is_age_restricted')->default(false);
            $table->tinyInteger('min_age')->nullable();
            $table->string('seo_title_en', 255)->nullable();
            $table->string('seo_title_ar', 255)->nullable();
            $table->text('seo_description_en')->nullable();
            $table->text('seo_description_ar')->nullable();
            // $table->timestampTz('made_available_at')->nullable();
            // $table->timestampTz('made_unavailable_at')->nullable();
            $table->uuid('updated_by_admin_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_countries');
    }
};
