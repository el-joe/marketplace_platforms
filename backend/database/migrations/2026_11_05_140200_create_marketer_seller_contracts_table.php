<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signed seller <-> marketer collaboration contracts. A separate concept from vendor_contracts (seller <-> platform)
     * and from the marketer's onboarding contract: it shares only the template storage.
     *
     * One row per (seller, campaign, marketer, template). `marketer_id` is NULL for the "open call" signature a seller
     * gives when a campaign is open to affiliates / brokers who are not known yet; it is copied onto each marketer who
     * later joins (parent_id points at the open-call row). Rendered text, variables and hash are frozen at signing.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketer_seller_contracts')) {
            return;
        }

        Schema::create('marketer_seller_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_template_id');
            $table->unsignedInteger('template_version');
            $table->uuid('vendor_id');
            $table->uuid('marketer_id')->nullable();
            $table->uuid('marketer_campaign_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->uuid('country_id')->nullable()->comment('The seller\'s country when signed.');
            $table->string('marketer_job_key', 60)->nullable()->comment('Type the contract was resolved for (open-call rows).');
            $table->enum('language_signed', ['en', 'ar'])->default('en');
            $table->enum('content_mode', ['editor', 'file'])->default('editor');
            $table->longText('rendered_content')->comment('Frozen contract text. Never updated after signing.');
            $table->char('rendered_content_hash', 64);
            $table->json('variables');
            $table->string('signed_file_path')->nullable();
            $table->string('signed_file_name')->nullable();
            $table->string('signed_file_mime', 100)->nullable();
            $table->uuid('signed_by_vendor_admin_id')->nullable();
            $table->string('signer_name', 150);
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->enum('status', ['active', 'superseded', 'revoked'])->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->timestamps();

            $table->string('active_key', 255)->nullable()->virtualAs(
                "IF(status = 'active', CONCAT_WS('|', vendor_id, IFNULL(marketer_campaign_id, ''), IFNULL(marketer_id, 'open'), contract_template_id), NULL)"
            );

            $table->foreign('contract_template_id', 'msc_template_foreign')->references('id')->on('classified_contract_templates')->restrictOnDelete();
            $table->foreign('vendor_id', 'msc_vendor_foreign')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('marketer_id', 'msc_marketer_foreign')->references('id')->on('marketers')->cascadeOnDelete();
            $table->foreign('marketer_campaign_id', 'msc_campaign_foreign')->references('id')->on('marketer_campaigns')->nullOnDelete();
            $table->foreign('parent_id', 'msc_parent_foreign')->references('id')->on('marketer_seller_contracts')->nullOnDelete();
            $table->foreign('country_id', 'msc_country_foreign')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('signed_by_vendor_admin_id', 'msc_signer_foreign')->references('id')->on('vendor_admins')->nullOnDelete();

            $table->unique('active_key', 'msc_one_active_signature_unique');
            $table->index(['marketer_id', 'status', 'signed_at'], 'msc_marketer_status_index');
            $table->index(['vendor_id', 'signed_at'], 'msc_vendor_signed_index');
            $table->index('contract_template_id', 'msc_template_index');
            $table->index('marketer_campaign_id', 'msc_campaign_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_seller_contracts');
    }
};
