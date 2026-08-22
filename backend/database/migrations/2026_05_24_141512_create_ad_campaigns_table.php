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
         * Table: ad_campaigns Each vendor's advertising campaign. One campaign can have multiple products and multiple keywords. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()seller_idUUID FK→seller_profilesNOcountry_idUUID FK→countriesNOnameVARCHAR(200)NO"Summer Electronics Push"typeVARCHAR(10)NOcpc, cpmstatusVARCHAR(20)NOdraftdraft, pending_review, active, paused, budget_exhausted, ended, rejectedbudget_total_centsBIGINTNOLifetime budget capbudget_daily_centsBIGINTYESNULLNULL = no daily capbudget_spent_total_centsBIGINTNO0Running totalbudget_spent_today_centsBIGINTNO0Reset by nightly jobbid_centsBIGINTNOMax CPC bid per click (or CPM per 1000)targeting_typeVARCHAR(20)NOautoauto, keyword, category, mixedstarts_atTIMESTAMPTZYESNULLNULL = start immediately on approvalends_atTIMESTAMPTZYESNULLNULL = run until budget exhaustedquality_scoreDECIMAL(3,2)NO5.001–10, calculated by platform. Affects rankingapproved_by_admin_idUUID FK→usersYESNULLapproved_atTIMESTAMPTZYESNULLrejection_reasonTEXTYESNULLcreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->uuid('country_id')->index();
            $table->string('name', 200);
            $table->enum('type', ['cpc', 'cpm']);
            $table->string('status', 20);
            $table->bigInteger('budget_total');
            $table->bigInteger('budget_daily')->nullable();
            $table->bigInteger('budget_spent_total')->default(0);
            $table->bigInteger('budget_spent_today')->default(0);
            $table->bigInteger('bid');
            $table->enum('targeting_type', ['auto', 'keyword', 'category', 'mixed']);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->decimal('quality_score', 3, 2);
            $table->uuid('approved_by_admin_id')->index()->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
