<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-16: marketer_contract_acceptances previously only
 * recorded a CUSTOMER accepting a marketer's public influencer contract
 * (Api\Customer\MarketerContractController). P-16 also needs the
 * MARKETER accepting their own onboarding contract with the platform
 * (marketers.global_status approval alone was not enough to unlock
 * campaigns per the enhancement.md verify list).
 *
 * Backfill-safe: customer_id is widened to nullable (existing rows keep
 * their value); marketer_id is added nullable so a row is either a
 * customer acceptance (marketer_id null) or a marketer's own acceptance
 * (customer_id null) — never both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_contract_acceptances', function (Blueprint $table) {
            $table->uuid('marketer_id')->nullable()->after('marketer_contract_version_id');
            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->index(['marketer_id', 'marketer_contract_version_id'], 'mca_marketer_version_index');
        });

        DB::statement('ALTER TABLE marketer_contract_acceptances MODIFY customer_id CHAR(36) NULL');
    }

    public function down(): void
    {
        Schema::table('marketer_contract_acceptances', function (Blueprint $table) {
            $table->dropForeign(['marketer_id']);
            $table->dropIndex('mca_marketer_version_index');
            $table->dropColumn('marketer_id');
        });
    }
};
