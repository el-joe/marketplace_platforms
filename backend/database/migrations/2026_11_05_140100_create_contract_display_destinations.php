<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * "Display On" destinations of seller-marketer collaboration contracts, and the links that tie a template to the
     * marketers it covers.
     *
     * - contract_display_destinations: database-driven list. `target_type` says what the destination is:
     *     general        the general contracts section (the seller's Legal documents),
     *     marketer_job   the profile of every marketer who holds the marketer_jobs row `marketer_job_key`
     *                    (influencer, broker, affiliate; "celebrity" is the Arabic wording of the influencer job),
     *     vendor_profile the seller's profile.
     *   A new marketer job needs one row here, no code change.
     * - contract_template_display_destination: template -> destinations (many to many).
     * - contract_template_marketers: optional narrowing / extra link to specific marketers.
     */
    public function up(): void
    {
        if (! Schema::hasTable('contract_display_destinations')) {
            Schema::create('contract_display_destinations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 60)->unique();
                $table->string('target_type', 24);
                $table->string('marketer_job_key', 60)->nullable();
                $table->string('name_en', 120);
                $table->string('name_ar', 120);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['target_type', 'marketer_job_key'], 'cdd_target_index');
            });
        }

        if (! Schema::hasTable('contract_template_display_destination')) {
            Schema::create('contract_template_display_destination', function (Blueprint $table) {
                $table->uuid('contract_template_id');
                $table->uuid('destination_id');
                $table->timestamps();

                $table->foreign('contract_template_id', 'ctdd_template_foreign')->references('id')->on('classified_contract_templates')->cascadeOnDelete();
                $table->foreign('destination_id', 'ctdd_destination_foreign')->references('id')->on('contract_display_destinations')->restrictOnDelete();
                $table->primary(['contract_template_id', 'destination_id'], 'ctdd_primary');
            });
        }

        if (! Schema::hasTable('contract_template_marketers')) {
            Schema::create('contract_template_marketers', function (Blueprint $table) {
                $table->uuid('contract_template_id');
                $table->uuid('marketer_id');
                $table->timestamps();

                $table->foreign('contract_template_id', 'ctm_template_foreign')->references('id')->on('classified_contract_templates')->cascadeOnDelete();
                $table->foreign('marketer_id', 'ctm_marketer_foreign')->references('id')->on('marketers')->cascadeOnDelete();
                $table->primary(['contract_template_id', 'marketer_id'], 'ctm_primary');
                $table->index('marketer_id', 'ctm_marketer_index');
            });
        }

        $this->seed();
    }

    /**
     * Idempotent: rows are matched by key and never overwritten, so renamed labels survive a re-run.
     */
    private function seed(): void
    {
        $rows = [
            ['general', 'general', null, 'General contracts section', 'قسم العقود العامة', 10],
            ['influencer_profile', 'marketer_job', 'influencer', 'Influencer / celebrity profile', 'ملف المؤثر / المشهور', 20],
            ['broker_profile', 'marketer_job', 'broker', 'Broker / intermediary profile', 'ملف الوسيط', 30],
            ['affiliate_profile', 'marketer_job', 'affiliate', 'Affiliate marketer profile', 'ملف المسوق بالعمولة', 40],
            ['seller_profile', 'vendor_profile', null, 'Seller profile', 'ملف البائع', 50],
        ];

        foreach ($rows as [$key, $type, $job, $nameEn, $nameAr, $sort]) {
            if (DB::table('contract_display_destinations')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('contract_display_destinations')->insert([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'target_type' => $type,
                'marketer_job_key' => $job,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_template_marketers');
        Schema::dropIfExists('contract_template_display_destination');
        Schema::dropIfExists('contract_display_destinations');
    }
};
